<?php
require_once 'vendor/autoload.php';
require_once 'init.php';

use Slim\Http\UploadedFile;


$app->get('/admin', function ($request, $response, $args) {
    $bookingList = DB::query("SELECT * FROM booking");

    return $this->view->render($response, '/admin/admin.html.twig', ['bookingList' => $bookingList]);
});


//Admin User List
$app->get('/admin/user/list[/{pageNo:[0-9]+}]', function ($request, $response, $args) {
    $pageNo = $args['pageNo'] ?? 1;
    $totalRecords = DB::queryFirstField("SELECT COUNT(*) AS COUNT FROM users");
    $totalPages = ceil($totalRecords / ROWS_PER_PAGE);
    return $this->view->render($response, '/admin/user_list.html.twig', [
        'maxPages' => $totalPages,
        'pageNo' => $pageNo,
    ]);
});

$app->get('/admin/user/list/singlepage/{pageNo:[0-9]+}', function ($request, $response, $args) {
    $pageNo = $args['pageNo'] ?? 1;
    $recordList = DB::query("SELECT * FROM users LIMIT %d OFFSET %d", ROWS_PER_PAGE, ($pageNo - 1) * ROWS_PER_PAGE);
    return $this->view->render($response, '/admin/user_singlepage.html.twig', ['userList' => $recordList]);
});

$app->get('/admin/user/{op:edit|add}[/{id:[0-9]+}]', function ($request, $response, $args) {
    $op = $args['op'];
    if (($op == 'add' && !empty($args['id'])) || ($op == 'edit' && empty($args['id']))) {
        $response = $response->withStatus(404);
        return $this->view->render($response, '404.html.twig');
    }
    if ($op == 'edit') {
        $user = DB::queryFirstRow("SELECT * FROM users WHERE id=%d", $args['id']);
        if (!$user) {
            $response = $response->withStatus(404);
            return $this->view->render($response, 'admin/not_found.html.twig');
        }
    } else {
        $user = [];
    }
    return $this->view->render($response, 'admin/users_addedit.html.twig', ['user' => $user, 'op' => $args['op']]);
});

//STAGE2&3:
$app->post('/admin/user/{op:edit|add}[/{id:[0-9]+}]', function ($request, $response, $args) use ($log) {
    $op = $args['op'];
    if (($op == 'add' && !empty($args['id'])) || ($op == 'edit' && empty($args['id']))) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/not_found.html.twig');
    }
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
    $roleType = $request->getParam('roleType');
    $pass1 = $request->getParam('pass1');
    $pass2 = $request->getParam('pass2');

    $errorList = [];

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
        if ($op == 'edit') {
            $user = DB::queryFirstRow('SELECT * FROM users WHERE email=%s AND id!=%d', $email, $args['id']);
        } else {
            $user = DB::queryFirstRow('SELECT * FROM users WHERE email=%s', $email);
        }

        if ($user) {
            $errorList[] = "Email has been already registered";
        }
    }
    if (!is_numeric($phoneNumber) || strlen($phoneNumber) != 10) {
        $errorList['phoneNumber'] = "phone number must be 10 digit number";
    }

    if (is_numeric($gender)) {
        $errorList['gender'] = "Gender must be selected";
    }

    if (is_numeric($roleType)) {
        $errorList['roleType'] = "Role Type must be selected";
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
        if ($op == 'edit') {
            $user = DB::queryFirstRow('SELECT * FROM users WHERE username=%s AND id!=%d', $username, $args['id']);
        } else {
            $user = DB::queryFirstRow('SELECT * FROM users WHERE username=%s', $username);
        }
        $log->debug(sprintf("fetch the record  with username=%s ", $username, $_SERVER['REMOTE_ADDR']));


        if ($user) {
            $errorList['username'] = "this username already in use";
        }
    }

    if ($op == 'add' || $pass1 != "") {
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
    }

    $valuesList = [
        'firstName' => $firstName, 'lastName' => $lastName,
        'gender' => $gender, 'email' => $email,
        'phoneNumber' => $phoneNumber, 'street' => $street,
        'city' => $city, 'province' => $province, 'roleType' => $roleType,
        'postalCode' => $postalCode, 'username' => $username
    ];

    if ($errorList) { //State2: errors
        return $this->view->render($response, 'admin/users_addedit.html.twig', ['errorList' => $errorList, 'user' => $valuesList]);
    } else { //state 3:success

        //Password Encripton Code
        global $passwordPepper;
        $pwdPeppered = hash_hmac("sha256", $pass1, $passwordPepper);
        $pwdHashed = password_hash($pwdPeppered, PASSWORD_DEFAULT);
        if ($op == 'add') {
            $valuesList = [
                'firstName' => $firstName, 'lastName' => $lastName,
                'gender' => $gender, 'email' => $email, 'roleType' => $roleType,
                'phoneNumber' => $phoneNumber, 'street' => $street,
                'city' => $city, 'province' => $province,
                'postalCode' => $postalCode, 'username' => $username, 'password' => $pwdHashed
            ];
            DB::insert('users', $valuesList);
            $log->debug(sprintf("new user created with Id=%s from IP=%s", DB::insertId(), $_SERVER['REMOTE_ADDR']));
            return $this->view->render($response, 'admin/operation_success.html.twig', ['op' => $op,  'object' => "user"]);
        } else {
            $valuesList = [
                'firstName' => $firstName, 'lastName' => $lastName,
                'gender' => $gender, 'email' => $email, 'roleType' => $roleType,
                'phoneNumber' => $phoneNumber, 'street' => $street,
                'city' => $city, 'province' => $province,
                'postalCode' => $postalCode, 'username' => $username
            ];
            if ($pass1 != "") {
                $valuesList['password'] = $pwdHashed;
            }
            DB::update('users', $valuesList, "id=%d", $args['id']);
            return $this->view->render($response, 'admin/operation_success.html.twig', ['op' => $op,  'object' => "user"]);
        }
    }
});

//Admin delete users
// STATE 1: first display
$app->get('/admin/user/delete/{id:[0-9]+}', function ($request, $response, $args) {
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id = %d", $args['id']);
    if (!$user) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/not_found.html.twig');
    }
    return $this->view->render($response, 'admin/users_delete.html.twig', ['user' => $user]);
});


$app->post('/admin/user/delete/{id:[0-9]+}', function ($request, $response, $args) {
    $user = DB::queryFirstRow("SELECT * FROM booking WHERE userId = %d", $args['id']);
    if ($user) {
        $message = "Cannot DELETE This User Because of History.  ";
        return $this->view->render($response, 'admin/operation_unsuccess.html.twig', ['object' => "user", 'message' => $message]);
    }
    DB::delete('users', "id = %d", $args['id']);
    return $this->view->render($response, 'admin/operation_success.html.twig', ['op' => "delete",  'object' => "user"]);
});


//Admin Cars List
$app->get('/admin/car/list[/{pageNo:[0-9]+}]', function ($request, $response, $args) {
    $pageNo = $args['pageNo'] ?? 1;
    $totalRecords = DB::queryFirstField("SELECT COUNT(*) AS COUNT FROM cars");
    $totalPages = ceil($totalRecords / ROWS_PER_PAGE);
    return $this->view->render($response, '/admin/car_list.html.twig', ['maxPages' => $totalPages, 'pageNo' => $pageNo]);
});

$app->get('/admin/car/list/singlepage/{pageNo:[0-9]+}', function ($request, $response, $args) {
    $pageNo = $args['pageNo'] ?? 1;
    $recordList = DB::query("SELECT * FROM cars LIMIT %d OFFSET %d", ROWS_PER_PAGE, ($pageNo - 1) * ROWS_PER_PAGE);
    return $this->view->render($response, '/admin/car_singlepage.html.twig', ['carList' => $recordList]);
});


$app->get('/admin/car/{op:add|edit}[/{id:[0-9]+}]', function ($request, $response, $args) {
    $op = $args['op'];
    if (($op == 'add' && !empty($args['id'])) || ($op == 'edit' && empty($args['id']))) {
        $response = $response->withStatus(404);
        return $this->view->render($response, '404.html.twig');
    }
    if ($op == 'edit') {
        $car = DB::queryFirstRow("SELECT * FROM cars WHERE id=%d", $args['id']);
        if (!$car) {
            $response = $response->withStatus(404);
            return $this->view->render($response, 'admin/not_found.html.twig');
        }
    } else {
        $car = [];
    }
    return $this->view->render($response, 'admin/cars_addedit.html.twig', ['car' => $car, 'op' => $args['op']]);
});

//STAGE2&3:
$app->post('/admin/car/{op:edit|add}[/{id:[0-9]+}]', function ($request, $response, $args) use ($log) {
    $op = $args['op'];
    if (($op == 'add' && !empty($args['id'])) || ($op == 'edit' && empty($args['id']))) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/not_found.html.twig');
    }
    $make = $request->getParam('make');
    $modelName = $request->getParam('modelName');
    $modelYear = $request->getParam('modelYear');
    $availability = $request->getParam('availability');


    $errorList = [];

    if (strlen($make) < 2 && strlen($make) < 30) {
        $errorList['make'] = "Make number must be between 2 and 30";
    }

    if (strlen($modelName) < 2 && strlen($modelName) < 30) {
        $errorList['modelName'] = "Model number must be between 2 and 30";
    }

    if (!is_numeric($modelYear)) {
        $errorList['modelYear'] = "modelYear must be Integer";
    }

    if (is_numeric($availability)) {
        $errorList['availability'] = "availabilty must be selected";
    }

    $imageFilePath = null;
    $uploadedImage = $request->getUploadedFiles()['imageFilePath'];
    if ($uploadedImage->getError() != UPLOAD_ERR_NO_FILE) {
        $result = verifyUploadedPhoto($imageFilePath, $uploadedImage);
        if ($result !== TRUE) {
            $errorList['imageFilePath'] = $result;
        }
    }

    $valuesList = [
        'make' => $make, 'modelName' => $modelName,
        'modelYear' => $modelYear, 'availability' => $availability,
        'imageFilePath' => $imageFilePath
    ];

    if ($errorList) { //State2: errors
        return $this->view->render($response, 'admin/cars_addedit.html.twig', ['errorList' => $errorList, 'car' => $valuesList]);
    } else { //state 3:success
        if ($imageFilePath != null) {
            $directory = $this->get('upload_directory');
            $imageFilePath = moveUploadedFile($directory, $uploadedImage);
        }
        if ($op == 'add') {
            $valuesList = [
                'make' => $make, 'modelName' => $modelName,
                'modelYear' => $modelYear, 'availability' => $availability,
                'imageFilePath' => $imageFilePath
            ];
            DB::insert('cars', $valuesList);
            $log->debug(sprintf("new car created with Id=%s from IP=%s", DB::insertId(), $_SERVER['REMOTE_ADDR']));
            return $this->view->render($response, 'admin/operation_success.html.twig', ['op' => $op,  'object' => "car"]);
        } else {
            $valuesList = [
                'make' => $make, 'modelName' => $modelName,
                'modelYear' => $modelYear, 'availability' => $availability
            ];
            if ($imageFilePath != "") {
                $valuesList['imageFilePath'] = $imageFilePath;
            }

            DB::update('cars', $valuesList, "id=%d", $args['id']);
            return $this->view->render($response, 'admin/operation_success.html.twig', ['op' => $op,  'object' => "car"]);
        }
    }
});


function moveUploadedFile($directory, UploadedFile $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8));
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

    return $filename;
}

function verifyUploadedPhoto(&$photoFilePath, $photo)
{
    if ($photo->getError() != 0) {
        return "Error uploading photo " . $photo->getError();
    }
    if ($photo->getSize() > 1024 * 1024) { // 1MB
        return "File too big. 1MB max is allowed.";
    }
    $info = getimagesize($photo->file);
    if (!$info) {
        return "File is not an image";
    }

    if ($info[0] < 100 || $info[0] > 1000 || $info[1] < 100 || $info[1] > 1000) {
        return "Width and height must be within 200-1000 pixels range";
    }
    $ext = "";
    switch ($info['mime']) {
        case 'image/jpeg':
            $ext = "jpg";
            break;
        case 'image/gif':
            $ext = "gif";
            break;
        case 'image/png':
            $ext = "png";
            break;
        default:
            return "Only JPG, GIF and PNG file types are allowed";
    }

    $photoFilePath = 'aaa' . $ext;
    return TRUE;
}


//Admin delete cars
// STATE 1: first display
$app->get('/admin/car/delete/{id:[0-9]+}', function ($request, $response, $args) {
    $car = DB::queryFirstRow("SELECT * FROM cars WHERE id = %d", $args['id']);
    if (!$car) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/not_found.html.twig');
    }
    return $this->view->render($response, 'admin/cars_delete.html.twig', ['car' => $car, 'object' => "car"]);
});


$app->post('/admin/car/delete/{id:[0-9]+}', function ($request, $response, $args) {
    $car = DB::queryFirstRow("SELECT * FROM rates WHERE carId = %d", $args['id']);
    if ($car) {
        $message = "Cannot DELETE This Car Because of History. ";
        return $this->view->render($response, 'admin/operation_unsuccess.html.twig', ['object' => "car", 'message' => $message]);
    }
    DB::delete('cars', "id = %d", $args['id']);
    return $this->view->render($response, 'admin/operation_success.html.twig', ['op' => "delete", 'object' => "car"]);
});


// Attach middleware that verifies only Admin can access /admin... URLs
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

// Function to check string starting 
// with given substring 
function startsWith($string, $startString)
{
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}

$app->add(function (ServerRequestInterface $request, ResponseInterface $response, callable $next) {
    $url = $request->getUri()->getPath();
    if (startsWith($url, "/admin")) {
        if (!isset($_SESSION['user']) || $_SESSION['user']['roleType'] == 'user') { // refuse if user not logged in AS ADMIN
            $response = $response->withStatus(403);
            return $this->view->render($response, 'admin/error_access_denied.html.twig');
        }
    }
    return $next($request, $response);
});


//Admin Rates

//Admin Rates List
$app->get('/admin/rate/list[/{pageNo:[0-9]+}]', function ($request, $response, $args) {
    $pageNo = $args['pageNo'] ?? 1;
    $totalRecords = DB::queryFirstField("SELECT COUNT(*) AS COUNT FROM rates");
    $totalPages = ceil($totalRecords / ROWS_PER_PAGE);
    return $this->view->render($response, '/admin/rate_list.html.twig', [
        'maxPages' => $totalPages,
        'pageNo' => $pageNo,
    ]);
});

$app->get('/admin/rate/list/singlepage/{pageNo:[0-9]+}', function ($request, $response, $args) {
    $pageNo = $args['pageNo'] ?? 1;
    $rateList = DB::query("SELECT * FROM rates LIMIT %d OFFSET %d", ROWS_PER_PAGE, ($pageNo - 1) * ROWS_PER_PAGE);
    return $this->view->render($response, '/admin/rate_singlepage.html.twig', ['rateList' => $rateList]);
});


$app->get('/admin/rate/add/{id:[0-9]+}', function ($request, $response, $args) {
    $rate = DB::queryFirstRow("SELECT * FROM rates WHERE carId=%d", $args['id']);
    if ($rate) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/recordFound.html.twig', ['id' => $args['id']]);
    }
    return $this->view->render($response, 'admin/rates_addedit.html.twig', ['rate' => $rate]);
});

$app->post('/admin/rate/add/{id:[0-9]+}', function ($request, $response, $args) {
    $carId = $args['id'];
    $byDay = $request->getParam('byDay');
    $byKilometer = $request->getParam('byKilometer');

    $errorList = [];

    if (!is_numeric($carId)) {
        $errorList['carId'] = "carId must be Integer";
    }

    if (!is_numeric($byDay)) {
        $errorList['byDay'] = "byDay must be Integer";
    }

    if (!is_numeric($byKilometer)) {
        $errorList['byKilometer'] = "byKilometer must be Integer";
    }

    $valuesList = [
        'carID' => $carId,
        'byDay' => $byDay, 'byKilometer' => $byKilometer
    ];

    if ($errorList) { //State2: errors
        return $this->view->render($response, 'admin/cars_addedit.html.twig', ['errorList' => $errorList, 'rate' => $valuesList]);
    } else { //state 3:success
        DB::insert('rates', $valuesList);
        return $this->view->render($response, 'admin/operation_success.html.twig', ['object' => "car"]);
    }
});


$app->get('/admin/rate/edit/{id:[0-9]+}', function ($request, $response, $args) {
    $rate = DB::queryFirstRow("SELECT * FROM rates WHERE id=%d", $args['id']);
    if (!$rate) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/not_found.html.twig');
    }
    return $this->view->render($response, 'admin/rates_addedit.html.twig', ['rate' => $rate]);
});

//STAGE2&3:
$app->post('/admin/rate/edit/{id:[0-9]+}', function ($request, $response, $args) use ($log) {

    $byDay = $request->getParam('byDay');
    $byKilometer = $request->getParam('byKilometer');

    $errorList = [];

    if (!is_numeric($byDay)) {
        $errorList['byDay'] = "byDay must be Integer";
    }

    if (!is_numeric($byKilometer)) {
        $errorList['byKilometer'] = "byKilometer must be Integer";
    }


    $valuesList = [
        'byDay' => $byDay, 'byKilometer' => $byKilometer
    ];

    if ($errorList) { //State2: errors
        return $this->view->render($response, 'admin/cars_addedit.html.twig', ['errorList' => $errorList, 'rate' => $valuesList]);
    } else { //state 3:success
        DB::update('rates', $valuesList, "id=%d", $args['id']);
        return $this->view->render($response, 'admin/operation_success.html.twig', ['object' => "rate"]);
    }
});

$app->get('/admin/rate/delete/{id:[0-9]+}', function ($request, $response, $args) {
    $rate = DB::queryFirstRow("SELECT * FROM rates WHERE id = %d", $args['id']);
    if (!$rate) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/not_found.html.twig');
    }
    return $this->view->render($response, 'admin/rates_delete.html.twig', ['rate' => $rate, 'object' => "rate"]);
});


$app->post('/admin/rate/delete/{id:[0-9]+}', function ($request, $response, $args) {

    DB::delete('rates', "id = %d", $args['id']);
    return $this->view->render($response, 'admin/operation_success.html.twig', ['op' => "delete", 'object' => "rate"]);
});

//Admin Reservation List
$app->get('/admin/booking/list[/{pageNo:[0-9]+}]', function ($request, $response, $args) {
    $pageNo = $args['pageNo'] ?? 1;
    $totalRecords = DB::queryFirstField("SELECT COUNT(*) AS COUNT FROM booking");
    $totalPages = ceil($totalRecords / ROWS_PER_PAGE);
    return $this->view->render($response, '/admin/booking_list.html.twig', [
        'maxPages' => $totalPages,
        'pageNo' => $pageNo,
    ]);
});

$app->get('/admin/booking/list/singlepage/{pageNo:[0-9]+}', function ($request, $response, $args) {
    $pageNo = $args['pageNo'] ?? 1;
    $bookingList = DB::query("SELECT * FROM booking LIMIT %d OFFSET %d", ROWS_PER_PAGE, ($pageNo - 1) * ROWS_PER_PAGE);
    return $this->view->render($response, '/admin/booking_singlepage.html.twig', ['bookingList' => $bookingList]);
});


$app->map(['GET', 'POST'], '/admin/booking/confirm/{id:[0-9]+}', function ($request, $response, $args) use ($log) {
    $booking = DB::queryFirstRow("SELECT * FROM booking WHERE id = %d", $args['id']);
    if (!$booking) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/not_found.html.twig');
    }
    $accepted =  "accepted";
    $valuesList = ['bookingStatus' => $accepted];
    DB::update('booking', $valuesList, "id=%i", $args['id']);
    return $this->view->render($response, 'admin/operation_success.html.twig', ['op' => "edit", 'object' => "booking"]);
});

$app->map(['GET', 'POST'], '/admin/booking/cancel/{id:[0-9]+}', function ($request, $response, $args) use ($log) {
    $booking = DB::queryFirstRow("SELECT * FROM booking WHERE id = %d", $args['id']);
    if (!$booking) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/not_found.html.twig');
    }
    $valuesList = ['bookingStatus' => "rejected"];
    DB::update('booking', $valuesList, "id=%i", $args['id']);
    return $this->view->render($response, 'admin/operation_success.html.twig', ['op' => "edit", 'object' => "booking"]);
});

$app->get('/admin/reports', function ($request, $response, $args) {

    $list = DB::query("SELECT count(*) AS count, make FROM cars GROUP BY make");
    $carCountList = array();
    $carMakeList = array();

    foreach ($list as $item) {
        array_push($carCountList, $item['count']);
        array_push($carMakeList, $item['make']);
    }

    $list2 = DB::query("SELECT sum(b.paymentAmount) AS sum, c.make FROM cars As c , booking As b WHERE b.carId = c.Id GROUP BY c.make");
    $carAmountList = array();
    $carIdList = array();

    foreach ($list2 as $item2) {
        array_push($carAmountList, $item2['sum']);
        array_push($carIdList, $item2['make']);
    }
    return $this->view->render($response, 'admin/reports.html.twig', ['carCountList' => $carCountList, 'carMakeList' => $carMakeList, 'carAmountList' => $carAmountList, 'carIdList' => $carIdList]);
});
