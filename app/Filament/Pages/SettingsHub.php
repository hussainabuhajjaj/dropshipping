<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Filament\Pages\BasePage;
use App\Filament\Resources\SiteSettingResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\FulfillmentProviderResource;
use App\Filament\Resources\ShippingZoneResource;
use App\Filament\Resources\NotificationTemplateResource;
use App\Filament\Pages\CJIntegration;
use App\Filament\Pages\Dashboard;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;

class SettingsHub extends BasePage
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static UnitEnum|string|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.settings-hub';

    /**
     * Get settings organized into logical groups
     */
    public function getSettingsGroups(): array
    {
        return [
            'store' => [
                'label' => 'Store',
                'icon' => 'heroicon-o-building-storefront',
                'items' => [
                    [
                        'label' => 'Details',
                        'description' => 'Name, branding, contact info',
                        'icon' => 'heroicon-o-information-circle',
                        'url' => SiteSettingResource::getUrl('index'),
                    ],
                    [
                        'label' => 'Files & Assets',
                        'description' => 'Logos, favicons, media uploads',
                        'icon' => 'heroicon-o-photo',
                        'url' => SiteSettingResource::getUrl('index'),
                    ],
                ],
            ],
            'selling' => [
                'label' => 'Selling',
                'icon' => 'heroicon-o-shopping-cart',
                'items' => [
                    [
                        'label' => 'Payments',
                        'description' => 'Gateways, refunds, payment logs',
                        'icon' => 'heroicon-o-banknotes',
                        'url' => PaymentResource::getUrl('index'),
                    ],
                    [
                        'label' => 'Checkout',
                        'description' => 'Taxes, pricing, customer accounts',
                        'icon' => 'heroicon-o-credit-card',
                        'url' => SiteSettingResource::getUrl('index'),
                    ],
                    [
                        'label' => 'Taxes & Duties',
                        'description' => 'Tax label, rate, included pricing',
                        'icon' => 'heroicon-o-receipt-percent',
                        'url' => SiteSettingResource::getUrl('index'),
                    ],
                ],
            ],
            'fulfillment' => [
                'label' => 'Fulfillment',
                'icon' => 'heroicon-o-truck',
                'items' => [
                    [
                        'label' => 'Shipping & Delivery',
                        'description' => 'Rates, zones, delivery window',
                        'icon' => 'heroicon-o-truck',
                        'url' => ShippingZoneResource::getUrl('index'),
                    ],
                    [
                        'label' => 'Locations',
                        'description' => 'Fulfillment providers',
                        'icon' => 'heroicon-o-map-pin',
                        'url' => FulfillmentProviderResource::getUrl('index'),
                    ],
                ],
            ],
            'communication' => [
                'label' => 'Communication',
                'icon' => 'heroicon-o-bell',
                'items' => [
                    [
                        'label' => 'Notifications',
                        'description' => 'Email/SMS templates',
                        'icon' => 'heroicon-o-bell',
                        'url' => NotificationTemplateResource::getUrl('index'),
                    ],
                    [
                        'label' => 'Policies',
                        'description' => 'Refund, privacy, terms',
                        'icon' => 'heroicon-o-document-text',
                        'url' => SiteSettingResource::getUrl('index'),
                    ],
                ],
            ],
            'integrations' => [
                'label' => 'Integrations',
                'icon' => 'heroicon-o-link',
                'items' => [
                    [
                        'label' => 'Suppliers',
                        'description' => 'CJ Dropshipping & other sources',
                        'icon' => 'heroicon-o-link',
                        'url' => CJIntegration::getUrl(),
                    ],
                    [
                        'label' => 'Analytics',
                        'description' => 'Sales, orders, fulfillment',
                        'icon' => 'heroicon-o-chart-bar',
                        'url' => Dashboard::getUrl(),
                    ],
                ],
            ],
        ];
    }
}
