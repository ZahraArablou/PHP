<?php
require_once 'vendor/autoload.php';
require_once 'init.php';

//*********************************  All  cars list ************************************/
$app->get('/carList[/{pageNo:[0-9]+}]', function ($request, $response, $args) {
    $pageNo = $args['pageNo'] ?? 1;
    $totalRecords = DB::queryFirstField("SELECT COUNT(*) AS COUNT FROM cars");
    $totalPages = ceil($totalRecords / ROWS_PER_PAGE);
    return $this->view->render($response, 'carList.html.twig', [
        'maxPages' => $totalPages,
        'pageNo' => $pageNo,
    ]);
});

$app->get('/carList/singlepage/{pageNo:[0-9]+}', function ($request, $response, $args) {
    $pageNo = $args['pageNo'] ?? 1;
    $carsList = DB::query("SELECT * FROM cars LIMIT %d OFFSET %d", ROWS_PER_PAGE, ($pageNo - 1) * ROWS_PER_PAGE);
    return $this->view->render($response, 'carList_singlepage.html.twig', ['carList' => $carsList, 'current' => 'car']);
})->setName('/carList');


//************************* available cars *******************************************

$app->get('/availableCars', function ($request, $response, $args) {

    $valuesList = ['pickupDate' =>  $_SESSION['pickupDate'], 'returnDate' =>  $_SESSION['returnDate']];
    return $this->view->render($response, 'availableCars.html.twig', ['v' => $valuesList]);
});
$app->post('/availableCars', function ($request, $response, $args) {

    $pickupDate = $request->getParam('pickupDate');
    echo $pickupDate;
    $returnDate = $request->getParam('returnDate');
    $numberOfDays = dateDifference($pickupDate, $returnDate);
    $_SESSION['pickupDate'] = $pickupDate;
    $_SESSION['returnDate'] = $returnDate;

    unset($_SESSION['nOD']);
    $_SESSION['nOD'] = $numberOfDays;
    $errorList = [];
    if ($returnDate == "") {
        $errorList[] = "Please enter return date";
    }
    if ($pickupDate == "") {
        $errorList[] = "Please enter pick up date";
    }
    if ($returnDate < $pickupDate) {
        $errorList[] = "Drop-off date must be after Pick-up date";
    }
    $now = date("Y-m-d");

    if ($pickupDate < $now || $returnDate < $now) {

        $errorList[] = "the date must be later than current date";
    }
    $valuesList = ['pickupDate' =>  $_SESSION['pickupDate'], 'returnDate' =>  $_SESSION['returnDate']];
    if ($errorList) { //State2: errors

        return $this->view->render($response, 'availableCars.html.twig', ['errorList' => $errorList, 'v' => $valuesList]);
    } else { //state 3:success
        //Check for availability cars  

        $carsList =  DB::query("SELECT * FROM cars  WHERE availability='available'");
        $carRate = DB::query("SELECT * FROM rates");

        $_SESSION['carsList'] =  $carsList;
        return $this->view->render($response, 'availableCars.html.twig', ['user' => $_SESSION['user'] ?? "",  'v' => $valuesList, 'list' => $carsList, 'flag' => 'true', 'r' => $carRate]);
    }
});
//*************************  book successful **********************************
$app->get('/bookSuccessfull/{id:[0-9]+}', function ($request, $response, $args) use ($log) {

    $values = [
        'pickupDate' =>  $_SESSION['pickupDate'], 'returnDate' =>  $_SESSION['returnDate'],
        'bookingStatus' =>  "pending", 'carID' => $_SESSION['carId'],
        'paymentAmount' =>  $_SESSION['paymentAmount'], 'rateType' =>  $_SESSION['rateType'],
        'rateValue' =>  $_SESSION['rateValue'], 'userId' => $args['id']
    ];

    unset($_SESSION['pickupDate']);
    unset($_SESSION['returnDate']);
    DB::insert('booking', $values);
    DB::update('cars', ['availability' => 'booked'], "id=%i", $_SESSION['carId']);
    $log->debug(sprintf("new booking created with Id=%s from IP=%s", DB::insertId(), $_SERVER['REMOTE_ADDR']));
    return $this->view->render($response, 'bookSuccessfull.html.twig', ['user' => $_SESSION['user']]);
});
/************************************ book cancel **************************************/

$app->get('/book_cancel/{bookid:[0-9]+}', function ($request, $response, $args) use ($log) {


    DB::update('booking', ['bookingStatus' => 'canceled'], "id=%i", $args['bookid']);
    $car = DB::queryOneRow("SELECT c.id FROM cars AS c join booking  AS b on c.id=b.carId WHERE  b.id=%i", $args['bookid']);
    DB::update('cars', ['availability' => 'available'], "id=%d", $car['id']);


    $log->debug(sprintf(" booking canceled from IP=%s",  $_SERVER['REMOTE_ADDR']));
    return $this->view->render($response, 'book_cancel.html.twig');
});


//***********************************user history ************************************** */
$app->get('/userHistory', function ($request, $response, $args) {
    $user = $_SESSION['user'];
    $booking = DB::query("SELECT B.id,C.make,C.modelName,C.modelYear,C.imageFilePath,B.pickupDate,B.returnDate,B.bookingStatus,B.paymentAmount,B.isPaid FROM booking AS B JOIN cars as C on B.carId=C.id where B.userId=%i order by B.id", $user['id']);
    // $booking = DB::query("SELECT sum(b.paymentAmount) AS sum, c.make FROM cars As c , booking As b WHERE b.carId = c.Id GROUP BY c.make");


    return $this->view->render($response, 'userHistory.html.twig', ['u' => $user, 'bookedlist' => $booking]);
});
//  handle the case when newBidPrice is not numerical at all, now it causes 404
//********************************** ajax **********************************
$app->get('/iskmtoolow/{km:[0-9\.]+}', function ($request, $response, $args) {
    //$oldBidPrice = DB::queryFirstField("SELECT lastBidPrice from auctions WHERE id=%d", $args['auctionId']);
    $km = $args['km'];
    if (is_numeric($km)) {
        if ($km < 15) {
            echo "kilometer must be  greater than 15";
        }
    } else {
        echo "km must be a number";
    }
});
//ajax
$app->get('/priceCalculate/{km:[0-9\.]+}/{rate:[0-9\.]+}', function ($request, $response, $args) {
    //$oldBidPrice = DB::queryFirstField("SELECT lastBidPrice from auctions WHERE id=%d", $args['auctionId']);
    $km = $args['km'];
    $rate = $args['rate'];

    if ($km) {
        echo $km * $rate;
    }
});
//ajax
$app->get('/priceCalculatebyDay/{carId:[0-9]+}', function ($request, $response, $args) {
    //$oldBidPrice = DB::queryFirstField("SELECT lastBidPrice from auctions WHERE id=%d", $args['auctionId']);
    $carRate = DB::queryFirstRow("SELECT * FROM rates WHERE carID=%d", $args['carId']);

    $carId = $args['carId'];
    if ($carId) {
        echo $_SESSION['nOD'] * $carRate['byDay'];
    }
});
//********************************* book details ******************************************
$app->get('/availableCars/{id:[0-9]+}', function ($request, $response, $args) {
    $selectedCar = DB::queryFirstRow("SELECT * FROM cars WHERE id=%d", $args['id']);
    $carRate = DB::queryFirstRow("SELECT * FROM rates WHERE carID=%d", $args['id']);
    $valuesList = ['pickupDate' =>  $_SESSION['pickupDate'], 'returnDate' =>  $_SESSION['returnDate']];
    if ($selectedCar && $carRate) {

        return $this->view->render($response, 'bookDetails.html.twig', ['c' => $selectedCar, 'r' => $carRate, 'v' => $valuesList]);
    } else { // not found - cause 404 here
        throw new \Slim\Exception\NotFoundException($request, $response);
    }
});
$app->POST('/availableCars/{id:[0-9]+}', function ($request, $response, $args) {
    $rate = $request->getParam('rate');
    $km = $request->getParam('km');
    $valuesList = ['pickupDate' =>  $_SESSION['pickupDate'], 'returnDate' =>  $_SESSION['returnDate']];
    $selectedCar = DB::queryFirstRow("SELECT * FROM cars WHERE id=%d", $args['id']);
    $carRate = DB::queryFirstRow("SELECT * FROM rates WHERE carID=%d", $args['id']);
    $price = 0;
    $errorList = [];
    if ($rate == "") {
        $errorList[] = "Rate must be chosen.";
    }
    if ($rate == "km") {
        if (!is_numeric($km)) {
            $errorList[] = "KM must be a number";
        }

        $price = $km * $carRate['byKilometer'];
        $_SESSION['rateValue'] = $carRate['byKilometer'];
    }

    if ($rate == "day") {
        $price = $_SESSION['nOD'] * $carRate['byDay'];
        $_SESSION['rateValue'] = $carRate['byDay'];
    }
    $_SESSION['paymentAmount'] = $price;
    $_SESSION['rateType'] = $rate;
    $_SESSION['carId'] = $args['id'];
    if ($errorList) {
        return $this->view->render($response, 'bookDetails.html.twig', ['error' => $errorList, 'rate' => $rate, 'c' => $selectedCar, 'r' => $carRate, 'v' => $valuesList]);
    }

    return $this->view->render($response, 'bookDetails.html.twig', ['price' => $price, 'rate' => $rate, 'c' => $selectedCar, 'r' => $carRate, 'u' => $_SESSION['user'], 'km' => $km, 'numberOfDay' => $_SESSION['nOD'], 'v' => $valuesList, 'flag' => 'true']);
});
