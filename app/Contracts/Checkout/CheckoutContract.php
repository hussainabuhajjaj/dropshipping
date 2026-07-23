<?php

declare(strict_types=1);

namespace App\Contracts\Checkout;

use App\Models\Cart;
use App\Models\Customer;

interface CheckoutContract
{
    /** Preview checkout costs: subtotal, shipping, tax, discounts, total. */
    public function preview(Cart $cart, ?Customer $customer): array;

    /** Validate the cart is ready for checkout. Returns array of error messages (empty = ready). */
    public function validate(Cart $cart, ?Customer $customer): array;

    /** Confirm the checkout and create an order from the cart. */
    public function confirm(Cart $cart, ?Customer $customer, array $checkoutData): object;
}
