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
//
//            if ($state !== csrf_token()) {
//                Log::error('AliExpress OAuth state mismatch', ['expected' => csrf_token(), 'received' => $state]);
//                return response('Invalid state parameter', 400);
//            }
//
//            if (!defined("IOP_SDK_WORK_DIR")) {
//                define("IOP_SDK_WORK_DIR", storage_path('logs/iop'));
//            }

            $url = config('ali_express.base_url');
            $appKey = config('ali_express.client_id');
            $appSecret = config('ali_express.client_secret');


            $apiPath = '/auth/token/create';
            $params = [
                'code' => $code,
                'app_key' => $appKey,
                'sign_method' => 'sha256',
                'timestamp' => now()->getTimestamp() * 1000,
                'sign' => 'AE3AB323878EE790908B4ED82C5F2D5B5EA4E72839C461E81CBD1757C2A82BEC',
                'partner_id' => 'iop-sdk-php', // Recommended to include
                'simplify' => 'false',
                'format' => 'json',
            ];

            ksort($params);

// Step B: Concatenate API Path + sorted Key-Value pairs
            $stringToSign = $apiPath;
            foreach ($params as $key => $value) {
                $stringToSign .= $key . $value;
            }

// Step C: Generate HMAC-SHA256 and convert to UPPERCASE
            $sign = strtoupper(hash_hmac('sha256', $stringToSign, $appSecret));

// 4. Add the sign to the parameter array
            $params['sign'] = $sign;

// 5. Send as a clean POST request (No query string in URL)
            $url = "https://api-sg.aliexpress.com/rest" . $apiPath . "?" . http_build_query($params);

            $response = Http::asForm()->get($url, $params);
            dd($response, $response->body());

//            $response = Http::asForm()
//                ->post($url . '/auth/token/create', [
//                    'app_key'     => $appKey,
//                    'timestamp'   => now()->timestamp,
//                    'sign_method' => 'sha256',
//                    'sign'        => $appSecret,
//                    'code'        => $code,
//                    'uuid'        =>  Str::uuid()->toString(),
//                ]);
//
//            $data = $response->json();

//            try {
//                $c = new \IopClient($url,$appKey,$appSecret);
//                $request = new \IopRequest('/auth/token/create' ,'POST');
//                $request->addApiParam('code',$code);
//                $request->addApiParam('uuid','uuid');
//
//                var_dump($c->execute($request));
////
////                $c = new \IopClient($url, $appKey, $appSecret);
////                $request = new \IopRequest('/auth/token/create');
////                $request->addApiParam('code', $code);
////                $request->addApiParam('uuid', Str::uuid()->toString());
////                var_dump($c->execute($request));
//            } catch (\Exception $e) {
//                dd($e , $e->getTrace());
//            }

            dd(12);

//            $url = config('ali_express.base_url');
//            $c = new \IopClient($url, config('ali_express.client_id'), config('ali_express.client_secret'));
//            $request = new \IopRequest('/auth/token/create');
//            $request->addApiParam('code',$code);
//            $request->addApiParam('uuid', Str::uuid());
//
//            $response = $c->execute($request);
//            dd($response);


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
}
