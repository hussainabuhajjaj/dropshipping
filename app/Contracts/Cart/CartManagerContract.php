<?php

declare(strict_types=1);

namespace App\Contracts\Cart;

use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Http\Request;

interface CartManagerContract
{
    /** Resolve or create a cart for the given request and optional customer. */
    public function resolveCart(Request $request, ?Customer $customer = null, bool $create = false): ?Cart;

    /** Merge a guest cart into a customer's cart after login. */
    public function mergeGuestCartIntoCustomer(Request $request, Customer $customer, ?string $legacySessionId = null): ?Cart;

    /** Get the best guest identifier token for the current request. */
    public function guestTokenForRequest(Request $request, ?Cart $cart = null): ?string;

    /** Resolve a visitor ID from request headers, input, or cookie. */
    public function resolveVisitorId(Request $request): ?string;
}
