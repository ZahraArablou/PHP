<?php
require_once 'vendor/autoload.php';
require_once 'init.php';


/*@var \slim\views\Twig $twig
$twig = Twig::create(__DIR__ . '/templates',['cache'=>__DIR__ . '/cache' , 'debug' =>true]);
$twig->getEnvironment()->addGlobal('session',$_SESSION);*/
// Define app routes below

$app->get('/internalerror', function ($request, $response, $args) {
    return $this->view->render($response, 'error_internal.html.twig');
});

$app->get('/session', function ($request, $response, $args) {
    echo "<pre>\n";
    print_r($_SESSION);
    return $response->write("");
});

function dateDifference($start_date, $end_date)
{
    // calulating the difference in timestamps 
    $diff = strtotime($start_date) - strtotime($end_date);

    // 1 day = 24 hours 
    // 24 * 60 * 60 = 86400 seconds
    return ceil(abs($diff / 86400));
}
//******************************** Home Page *************************************************
// STATE 1: first display of the form

$app->get('/', function ($request, $response, $args) {
    global $currentpage;
    $currentpage = "/";

    return $this->view->render($response, 'home.html.twig', ['current' => 'home']);
})->setName('/');

//****************************************** login ********************************************

$app->get('/login', function ($request, $response, $args) {
    //  return $response->write("Hello " . $args['name']);

    // DB::insert('people', ['name' => $name, 'age' => $age]);
    return $this->view->render($response, 'login.html.twig');
})->setName('/login');

//STATE2&3:RECIEVING SUBMISSION
$app->post('/login', function ($request, $response, $args) use ($log) {
    unset($_SESSION['pickupDate']);
    unset($_SESSION['returnDate']);
    unset($_SESSION['user']);
    $username = $request->getParam('username');
    $password = $request->getParam('password');

    $errorList = [];

    $valuesList = ['username' => $username, 'password' => $password];

    $user = DB::queryFirstRow('SELECT * FROM users WHERE username=%s', $username);
    $loginSuccess = false;

    if ($user) {
        global $passwordPepper;
        $pwdPeppered = hash_hmac("sha256", $password, $passwordPepper);
        $pwdHashed = $user['password'];
        if (password_verify($pwdPeppered, $pwdHashed)) {
            $loginSuccess = true;
        }
    }
    if (!$loginSuccess) {
        $log->info(sprintf("Login failed for username %s from %s", $username, $_SERVER['REMOTE_ADDR']));
        return $this->view->render($response, 'login.html.twig', ['error' => true]);
    } else {
        unset($user['password']); //for security reasons remove password from session 
        $_SESSION['user'] = $user; //remember user logged in
        setFlashMessage("Login successful");
        $log->info(sprintf("Login succcess ful for username %s, user id=%d from %s", $username, $user['id'], $_SERVER['REMOTE_ADDR']));
        return $this->view->render($response, 'home.html.twig', ['userSession' => $_SESSION['user']]);
    }
});
//************************************* log out ********************************************* */

$app->get('/logout', function ($request, $response, $args) use ($log) {

    unset($_SESSION['user']);
    unset($_SESSION['pickupDate']);
    unset($_SESSION['returnDate']);

    setFlashMessage("You've been logged out");
    
    //flashcart
    $log->debug(sprintf("Logout successful for uid=%d, from %s", @$_SESSION['user']['id'], $_SERVER['REMOTE_ADDR']));
    return $this->view->render($response, 'logout.html.twig');
})->setName('/logout');

$app->get('/isusernametaken/{userId:[0-9]+}/{newusername:[0-9\.]+}', function ($request, $response, $args) {
    $newusername = $_GET['username'];
    $result =  DB::queryFirstField("SELECT * FROM users WHERE username='%s'", $newusername);


    if ($result) {
        echo "User name already registered.";
    }
});

//*********************************** register user ******************************************************
$app->get('/register', function ($request, $response, $args) {

    return $this->view->render($response, 'register.html.twig');
})->setName('/register');

//STATE2&3:RECIEVING SUBMISSION
$app->post('/register', function ($request, $response, $args) use ($log) {
    $firstName = $request->getParam('firstName');
    $lastName = $request->getParam('lastName');
    $gender = $request->getParam('gender');
    $email = $request->getParam('email');
    $phoneNumber = $request->getParam('phoneNumber');
    $street = $request->getParam('street');
    $city = $request->getParam('city');
    $province = $request->getParam('province');
    $postalCode = $request->getParam('postalCode');
    $username = $request->getParam('username');
    $pass1 = $request->getParam('pass1');
    $pass2 = $request->getParam('pass2');

    $errorList = [];
    if (is_numeric($gender)) {
        $errorList['gender'] = "Gender must be selected";
    }

    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,50}$/', $firstName) !== 1) {
        $errorList['firstName'] = "first name must be 2-50 characters long made up of letters, digits, space, comma, dot, dash";
    }
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,50}$/', $lastName) !== 1) {
        $errorList['lastName'] = "last name must be 2-50 characters long made up of letters, digits, space, comma, dot, dash";
    }
    if (preg_match('/^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/', $postalCode) !== 1) {
        $errorList['postalCode'] = "Postal Code in not valid";
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errorList['email'] = "Email doesnot look valid";
    } else { //if email has been already registered
        $user = DB::queryFirstRow('SELECT * FROM users WHERE email=%s', $email);

        if ($user) {
            $errorList['email'] = "Email has been already registered";
        }
    }
    if (!is_numeric($phoneNumber) || strlen($phoneNumber) != 10) {
        $errorList['phoneNumber'] = "phone number must be 10 digit number";
    }
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,50}$/', $street) !== 1) {
        $errorList['street'] = "street must be 2-50 characters long made up of letters, digits, space, comma, dot, dash";
    }
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,50}$/', $city) !== 1) {
        $errorList['city'] = "city must be 2-50 characters long made up of letters, digits, space, comma, dot, dash";
    }
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,50}$/', $province) !== 1) {
        $errorList['province'] = "province must be 2-50 characters long made up of letters, digits, space, comma, dot, dash";
    }
    if (preg_match('/^[a-zA-Z0-9]{4,20}$/', $username) != 1) {
        $errorList['username'] = "Username mustbe 4-20 characters long made up of lower-case characters and numbers";
    } else { //but is this username already in use?
        $user = DB::queryFirstRow('SELECT * FROM users WHERE username=%s', $username);
        $log->debug(sprintf("fetch the record  with username=%s ", $username, $_SERVER['REMOTE_ADDR']));

        if ($user) {
            $errorList['username'] = "this username already in use";
        }
    }
    if ($pass2 == "") {
        $errorList['passwordmatch'] = "Password must be 6-8 characters long,"
            . "With at least one uppercase , one lowercase, and one digit in it";
    }
    if ($pass1 != $pass2) {
        $errorList['passwordmatch'] = "Passwords do not match";
        $pass1 = "";
        $pass2 = "";
    } else {
        if (
            strlen($pass1) < 6 || strlen($pass2) > 8
            || (preg_match("/[A-Z]/", $pass1) == FALSE)
            || (preg_match("/[a-z]/", $pass1) == FALSE)
            || (preg_match("/[0-9]/", $pass1) == FALSE)
        ) {
            $errorList['password'] = "Password must be 6-8 characters long,"
                . "With at least one uppercase , one lowercase, and one digit in it";
        }
    }

    $valuesList = [
        'firstName' => $firstName, 'lastName' => $lastName,
        'gender' => $gender, 'email' => $email,
        'phoneNumber' => $phoneNumber, 'street' => $street,
        'city' => $city, 'province' => $province,
        'postalCode' => $postalCode, 'username' => $username, 'password' => $pass1
    ];
    if ($errorList) { //State2: errors

        return $this->view->render($response, 'register.html.twig', ['errorList' => $errorList, 'user' => $valuesList]);
    } else { //state 3:success

        //Password Encripton Code
        global $passwordPepper;
        $pwdPeppered = hash_hmac("sha256", $pass1, $passwordPepper);
        $pwdHashed = password_hash($pwdPeppered, PASSWORD_DEFAULT);

        $valuesList = [
            'firstName' => $firstName, 'lastName' => $lastName,
            'gender' => $gender, 'email' => $email,
            'phoneNumber' => $phoneNumber, 'street' => $street,
            'city' => $city, 'province' => $province,
            'postalCode' => $postalCode, 'username' => $username, 'password' => $pwdHashed
        ];
        DB::insert('users', $valuesList);
        $log->debug(sprintf("new user created with Id=%s from IP=%s", DB::insertId(), $_SERVER['REMOTE_ADDR']));
        setFlashMessage("account created successfully.",);
        return $this->view->render($response, 'register_success.html.twig');
    }
});

//Contact Page
// STATE 1: first display of the form
$app->get('/contact', function ($request, $response, $args) {
    return $this->view->render($response, 'contact.html.twig', ['current' => 'contact']);
})->setName('/contact');
//*********************************** user edit profile ************************************** */

$app->get('/user_editprofile', function ($request, $response, $args) use ($log) {

    return $this->view->render($response, 'user_editprofile.html.twig', ['user' => $_SESSION['user']]);
});


$app->post('/user_editprofile', function ($request, $response, $args) use ($log) {

    $firstName = $request->getParam('firstName');
    $lastName = $request->getParam('lastName');
    $gender = $request->getParam('gender');
    $email = $request->getParam('email');
    $phoneNumber = $request->getParam('phoneNumber');
    $street = $request->getParam('street');
    $city = $request->getParam('city');
    $province = $request->getParam('province');
    $postalCode = $request->getParam('postalCode');

    $errorList = [];

    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,50}$/', $firstName) !== 1) {
        $errorList['firstName'] = "first name must be 2-50 characters long made up of letters, digits, space, comma, dot, dash";
    }
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,50}$/', $lastName) !== 1) {
        $errorList['lastName'] = "last name must be 2-50 characters long made up of letters, digits, space, comma, dot, dash";
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errorList['email'] = "Email doesnot look valid";
    }
    if (!is_numeric($phoneNumber) || strlen($phoneNumber) != 10) {
        $errorList['phoneNumber'] = "phone number must be 10 digit number";
    }
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,50}$/', $street) !== 1) {
        $errorList['street'] = "street must be 2-50 characters long made up of letters, digits, space, comma, dot, dash";
    }
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,50}$/', $city) !== 1) {
        $errorList['city'] = "city must be 2-50 characters long made up of letters, digits, space, comma, dot, dash";
    }
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,50}$/', $province) !== 1) {
        $errorList['province'] = "province must be 2-50 characters long made up of letters, digits, space, comma, dot, dash";
    }



    $valuesList = [
        'firstName' => $firstName, 'lastName' => $lastName,
        'gender' => $gender, 'email' => $email,
        'phoneNumber' => $phoneNumber, 'street' => $street,
        'city' => $city, 'province' => $province,
        'postalCode' => $postalCode
    ];
    if ($errorList) { //State2: errors

        return $this->view->render($response, 'user_editprofile.html.twig', ['errorList' => $errorList, 'user' => $valuesList]);
    } else { //state 3:success

        $user = $_SESSION['user'];
        DB::update('users', $valuesList, "id=%d", $user['id']);
        $log->debug(sprintf("new user updated with Id=%s from IP=%s", DB::insertId(), $_SERVER['REMOTE_ADDR']));
        setFlashMessage("Information successfully updated.");

        $user1 = DB::queryFirstRow("SELECT * FROM users WHERE id=%d", $user['id']);
        unset($_SESSION['user']);
        $_SESSION['user'] = $user1;

        //return $this->view->render($response, 'home.html.twig');    
        return $this->view->render($response, 'user_update_success.html.twig');
    }
});

$app->get('/user_update_success', function ($request, $response, $args) {

    return $this->view->render($response, 'user_update_success.html.twig');
});
