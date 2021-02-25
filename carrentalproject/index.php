<?php

require_once 'vendor/autoload.php';
require_once 'init.php';

require_once 'user_login.php';

require_once 'book_car.php';

require_once 'payment.php';


require_once 'password_Forgot.php';

require_once 'admin.php';
// Run app - must be the last operation
// if you forget it all you'll see is a blank page
$app->run();
