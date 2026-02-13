<?php return array (
  'App\\Providers\\EventServiceProvider' => 
  array (
    'Illuminate\\Auth\\Events\\Login' => 
    array (
      0 => 'App\\Listeners\\Auth\\LogAdminLogin',
    ),
    'Illuminate\\Auth\\Events\\Registered' => 
    array (
      0 => 'Illuminate\\Auth\\Listeners\\SendEmailVerificationNotification',
    ),
    'App\\Events\\Orders\\OrderPlaced' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendOrderConfirmedNotification',
    ),
    'App\\Events\\Orders\\OrderPaid' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendOrderConfirmedNotification',
    ),
    'App\\Events\\Orders\\OrderShipped' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendOrderShippedNotification',
    ),
    'App\\Events\\Orders\\FulfillmentDelayed' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendShippingDelayNotification',
    ),
    'App\\Events\\Orders\\CustomsUpdated' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendCustomsInfoNotification',
    ),
    'App\\Events\\Orders\\OrderDelivered' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendDeliveryConfirmedNotification',
    ),
    'App\\Events\\Orders\\OrderCancellationRequested' => 
    array (
      0 => 'App\\Listeners\\Orders\\HandleOrderCancellation',
    ),
    'App\\Events\\Orders\\RefundProcessed' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendRefundProcessedNotification',
    ),
    'App\\Events\\Orders\\ReturnApproved' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendReturnApprovedNotification',
    ),
    'App\\Events\\Orders\\ReturnRejected' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendReturnRejectedNotification',
    ),
    'App\\Events\\Customers\\CustomerRegistered' => 
    array (
      0 => 'App\\Listeners\\Customers\\SendWelcomeNotification',
    ),
  ),
  'Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider' => 
  array (
    'App\\Events\\Customers\\CustomerRegistered' => 
    array (
      0 => 'App\\Listeners\\Customers\\SendWelcomeNotification@handle',
    ),
    'Illuminate\\Auth\\Events\\Login' => 
    array (
      0 => 'App\\Listeners\\Auth\\LogAdminLogin@handle',
    ),
    'App\\Events\\Orders\\OrderShipped' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendOrderShippedNotification@handle',
      1 => 'App\\Listeners\\Orders\\SendOrderShippedNotificationListener@handle',
    ),
    'App\\Events\\Orders\\FulfillmentDelayed' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendShippingDelayNotification@handle',
    ),
    'App\\Events\\Orders\\OrderCancellationRequested' => 
    array (
      0 => 'App\\Listeners\\Orders\\HandleOrderCancellation@handle',
    ),
    'App\\Events\\Orders\\OrderPlaced' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendOrderConfirmedNotification@handle',
    ),
    'App\\Events\\Orders\\OrderPaid' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendOrderConfirmedNotification@handle',
    ),
    'App\\Events\\Orders\\ReturnApproved' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendReturnApprovedNotification@handle',
    ),
    'App\\Events\\Orders\\CustomsUpdated' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendCustomsInfoNotification@handle',
    ),
    'App\\Events\\Orders\\OrderDelivered' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendDeliveryConfirmedNotification@handle',
    ),
    'App\\Events\\Orders\\ReturnRejected' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendReturnRejectedNotification@handle',
    ),
    'App\\Events\\Orders\\RefundProcessed' => 
    array (
      0 => 'App\\Listeners\\Orders\\SendRefundProcessedNotification@handle',
    ),
  ),
);