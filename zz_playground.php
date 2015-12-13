<?php
require_once 'app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);

// any code you want here....


$class = $bootstrap->getObjectManager()->create('Namespace\Module\Model\Test');
