<?php
    include "../IopSdk.php";

    $c = new IopClient('https://api-pre.aliexpress.com/sync', '33505222', 'e1fed6b34feb26aabc391d187732af93');
    $request = new IopRequest('aliexpress.logistics.redefining.getlogisticsselleraddresses');
    $request->simplify="true";
    $request->format="xml";
    $request->addApiParam('seller_address_query','pickup');
    
    var_dump($c->execute($request,"50000001a27l15rndYBjw6PrtFFHPGZfy09k1Cp1bd8597fsduP0RsNy0jhF6FL"));

?>