<?php

require_once 'vendor/autoload.php';

$dp = new Le\Dataplater\Dataplater(template: <<<END
<!doctype html>
<html lang=en><body>
<ul>
    <template data-dp-foreach=['first','second','third'] data-dp-var=site data-dp-key=number>
        <li data-dp=number+1~'-'~site></li>
    </template>
    
    <li data-dp-if=false>this will disappear</li>
</ul>
<div data-dp=name></div>
<span data-dp=test?:'custom'>default</span>
<var data-dp=1+3></var>
</body></html>
END);

echo $dp->render([
    'name' => 'John',
    'age' => 30,
    'test' => '',
]);