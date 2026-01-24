<?php

namespace App\Http\Controllers;

use App\Models\AliExpressToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AliExpressOAuthController extends Controller
{
    public function redirect()
    {
        $query = http_build_query([
            'response_type' => 'code',
            "force_auth" => true,
            'redirect_uri' => config('ali_express.redirect_uri'),
            'client_id' => config('ali_express.client_id'),
//            'state' => csrf_token(),
            'sp' => 'ae', // Important for AliExpress
        ]);
        $url = config('ali_express.base_url') . '/oauth/authorize?' . $query;
        return redirect()->away($url);
    }

    public function createSystemToken(Request $request)
    {
        $code = $request->input('code');
        if (!$code) {
            return response()->json(['error' => 'Missing code'], 400);
        }
        $client = new \App\Infrastructure\Fulfillment\Clients\AliExpressClient(
            config('ali_express.client_id')
        );
        $result = $client->createToken($code);
        return response()->json($result);
    }

    public function callback(Request $request)
    {
        try {
            $code = $request->input('code');
//            $state = $request->input('state');

            if (!$code) {
                Log::error('AliExpress OAuth callback missing code');
                return response('Missing authorization code', 400);
            }

            $appKey = config('ali_express.client_id');
            $appSecret = config('ali_express.client_secret');
            $apiPath = '/auth/token/create'; // MANDATORY for signature

            $params = [
                'app_key' => $appKey,
                'code' => $code,
                'sign_method' => 'sha256',
                'timestamp' => round(microtime(true) * 1000),
            ];

            ksort($params);
            $signature = $this->aliExpressSign($params, $appSecret, $apiPath);

            $params['sign'] = $signature;

            $response = Http::asForm()->post("https://api-sg.aliexpress.com/rest" . $apiPath, $params);

            $body = $response->json();
            dd($body);
            $body = json_decode($body, true);

            $response = Http::asForm()->post(config('ali_express.base_url') . '/auth/token/create', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => config('ali_express.client_id'),
                'client_secret' => config('ali_express.client_secret'),
                'redirect_uri' => config('ali_express.redirect_uri'),
            ]);

            $data = $response->json();

            dd($data);
            Log::info('AliExpress OAuth token response received', ['expires_in' => $data['expires_in'] ?? null]);

            if (!isset($data['access_token'])) {
                Log::error('AliExpress OAuth failed: no access_token in response', $data);
                return response('Failed to obtain access token: ' . ($data['message'] ?? 'Unknown error'), 400);
            }

            $token = AliExpressToken::create([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                'refresh_expires_at' => isset($data['refresh_expires_in']) ? now()->addSeconds($data['refresh_expires_in']) : null,
                'raw' => json_encode($data),
            ]);

            // Store in settings for Filament/CLI access
            \App\Models\Setting::setSetting([
                'aliexpress_access_token' => $data['access_token'],
                'aliexpress_refresh_token' => $data['refresh_token'] ?? null,
                'aliexpress_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
            ]);

            Log::info('AliExpress token stored successfully', ['token_id' => $token->id]);
            // Redirect back to ali-express-import page with access token in query string
            return redirect('/ali-express-import?access_token=' . urlencode($data['access_token']));
        } catch (\Exception $e) {
            Log::error('AliExpress OAuth callback error', ['error' => $e->getMessage()]);
            return response('Authentication error: ' . $e->getMessage(), 500);
        }
    }

    public function refresh()
    {
        try {
            $token = AliExpressToken::getLatestToken();

            if (!$token) {
                Log::error('No AliExpress token found for refresh');
                return response()->json(['error' => 'No token found'], 400);
            }

            if (!$token->refresh_token) {
                Log::error('No refresh_token available');
                return response()->json(['error' => 'Cannot refresh: no refresh_token'], 400);
            }

            $response = Http::asForm()->post('https://api-sg.aliexpress.com/rest/auth/token/create', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
                'client_id' => config('ali_express.client_id'),
                'client_secret' => config('ali_express.client_secret'),
            ]);

            $data = $response->json();
            Log::info('AliExpress token refresh response', ['expires_in' => $data['expires_in'] ?? null]);

            if (!isset($data['access_token'])) {
                Log::error('Token refresh failed', $data);
                return response()->json(['error' => 'Failed to refresh token'], 400);
            }

            $token->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
                'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                'raw' => json_encode($data),
            ]);

            Log::info('AliExpress token refreshed successfully');
            return response()->json(['success' => true, 'message' => 'Token refreshed']);
        } catch (\Exception $e) {
            Log::error('Token refresh error', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Generate AliExpress API signature (HMAC-SHA256)
     *
     * @param array $params All request params (system + business), EXCEPT sign
     * @param string $appSecret App Secret
     * @param string $apiName API name or api_path
     * @param bool $isSystem true = System Interface, false = Business Interface
     *
     * @return string Uppercase HEX signature
     */
    public function aliExpressSign(
        array  $params,
        string $appSecret,
        string $apiName,
        bool   $isSystem = true
    ): string
    {
        // 1. If Business Interface → api_path participates in sorting
        if (!$isSystem) {
            // api_path is usually passed as "method"
            $params['method'] = $apiName;
        }

        // Remove sign if exists
        unset($params['sign']);

        // 2. Sort parameters by ASCII order of key
        ksort($params);

        // 3. Concatenate parameters
        $stringToSign = '';

        // System Interface → prepend API name
        if ($isSystem) {
            $stringToSign .= $apiName;
        }

        foreach ($params as $key => $value) {
            if ($key === '' || $value === '' || $value === null) {
                continue;
            }
            $stringToSign .= $key . $value;
        }

        // 4. HMAC-SHA256
        $hash = hash_hmac(
            'sha256',
            $stringToSign,
            $appSecret,
            true // raw binary
        );

        // 5. Convert to UPPERCASE HEX
        return strtoupper(bin2hex($hash));
    }
}
