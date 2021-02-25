<?php
require_once 'vendor/autoload.php';
require_once 'init.php';
//********************** password reset ***************************** */
/*****************Forgot password use email to reset****************************/
$app->get('/forgotpassword', function ($request, $response, $args) {
    return $this->view->render($response, 'forgotpassword.html.twig');
});

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


$app->post('/forgotpassword', function ($request, $response, $args) {

    $errorList = array();
    $mail = new PHPMailer(true);
    $email = $request->getParam('email');
    $_SESSION['email'] = $email;
    if ($email) {
        $record = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($record) {
            $subject = "Reset your password";
            $uid = $record['id'];
            $token = md5($uid . $record['email'] . $record['password']);
            $url = "password_reset_action/email=" . $email . "&token=" . $token;
            $time = date('Y-m-d H:i');
            $name = "Reset password";
            $msg = "Hi " . $email . ":<br> you are in " . $time . ' submited reset password, please click below link. <br>
                    <a href="http://carrentalproject.local:8888/password_reset_action/email=' . $email . '">' . $url . '</a>';
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host       = 'smtp.mailtrap.io';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'arablouzahra@gmail.com';
            $mail->Password   = '834fcf23a6d574';
            $mail->SMTPSecure = 'ssl';
            $mail->Port       = 587;
            $mail->setFrom($email, $name);
            $mail->addAddress($email);
            $mail->addReplyTo($email, 'Information');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $msg;
            if ($mail->send()) {
                return $this->view->render($response, 'password_reset_emailsent.html.twig', ['v' => ['email' => $email]]);
            } else {
                return $this->view->render($response, 'password_reset_emailsentfailed.html.twig');
            }
        } else {
            array_push($errorList, "Please enter correct email address");
            return $this->view->render($response, 'forgotpassword.html.twig', ['errorList' => $errorList]);
        }
    }
});

$app->map(['GET', 'POST'], '/password_reset_action/{email}', function ($request, $response, $args) {
    $email = $args['email'];
    if ($email = $_SESSION['email']) {
        $pass1 = $request->getParam('pass1');
        $pass2 = $request->getParam('pass2');
        $errorList = array();
        $record = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($record) {
            if ($pass1 != $pass2) {
                array_push($errorList, "Passwords do not match.");
            } else {
                if ((strlen($pass1) < 6) || (strlen($pass1) > 100)
                    || (preg_match("/[A-Z]/", $pass1) == FALSE)
                    || (preg_match("/[a-z]/", $pass1) == FALSE)
                    || (preg_match("/[0-9]/", $pass1) == FALSE)
                ) {
                    array_push($errorList, "Password must be 6-100 characters long with at least one uppercase, one lowercase, and one digit in it");
                }
            }
            if ($errorList) {
                return $this->view->render($response, 'password_reset_action.html.twig', ['errorList' => $errorList, 'v' => ['email' => $email]]);
            } else {
                DB::update('users', ['password' => $pass1], "email=%s", $email);
                return $this->view->render($response, 'password_reset_action_success.html.twig');
            }
        } else {
            array_push($errorList, "Email is not correct");
            return $this->view->render($response, 'password_reset_action.html.twig', ['errorList' => $errorList]);
        }
    }
});




function getLatLong($code)
{
    $mapsApiKey = 'AIzaSyBON6db-E8cIzVii1TuqKult0KLq9zvrDE';
    $query = "https://maps.googleapis.com/maps/api/geocode/json?&address=" . urlencode($code) . "&key=" . $mapsApiKey;
    $geocodeFromPoco = file_get_contents($query);
    $output = json_decode($geocodeFromPoco);

    // convert into readable format
    $data['latitude'] = $output->results[0]->geometry->location->lat;
    $data['longitude'] = $output->results[0]->geometry->location->lng;
    //Return latitude and longitude of the given postalcode
    if (!empty($data)) {
        return $data;
    } else {
        return false;
    }
}



//***********************************Teacher password change code ********************* */
/*
$app->get('/passreset_request', function ($request, $response,$args) {
    // $view = Twig::fromRequest($request);
    return $this->view->render($response,'password_reset.html.twig');
});

use Slim\Http\Request;
use Slim\Http\Response;

$app->post('/passreset_request', function (Request $request, Response $response) {
    global $log;
    // $view = Twig::fromRequest($request);
    // $post = $request->getParsedBody();
    $email = $request->getParam('email');
    // $email = filter_var($email, FILTER_VALIDATE_EMAIL); // 'FALSE' will never be found anyway
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if ($user) { // send email
        $secret = generateRandomString(60);
        $dateTime = gmdate("Y-m-d H:i:s"); // GMT time zone
        DB::insertUpdate('passwordreset', [
                'userId' => $user['id'],
                'secret' => $secret,
                'creationTS' => $dateTime
            ], [
                'secret' => $secret,
                'creationTS' => $dateTime
            ]);
        //
        // primitive template with string replacement
        $emailBody = file_get_contents('templates/password_reset_email.html.strsub');
        $emailBody = str_replace('EMAIL', $email, $emailBody);
        $emailBody = str_replace('SECRET', $secret, $emailBody);
        /* // OPTION 1: PURE PHP EMAIL SENDING - most likely will end up in Spam / Junk folder
        $to = $email;
        $subject = "Password reset";
        // Always set content-type when sending HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        // More headers
        $headers .= 'From: No Reply <noreply@teacher.ip20.com>' . "\r\n";
        // finally send the email
        $result = mail($to, $subject, $emailBody, $headers);
        if ($result) {
            $log->debug(sprintf("Password reset sent to %s", $email));
        } else {
            $log->error(sprintf("Error sending password reset email to %s\n:%s", $email));
        } 
        // end of option 1 code */

 /*     // OPTION 2: USING EXTERNAL SERVICE - should not land in Spam / Junk folder 
        $config = SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key',
            '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');
        $apiInstance = new SendinBlue\Client\Api\SMTPApi(new GuzzleHttp\Client(), $config);
        // \SendinBlue\Client\Model\SendSmtpEmail | Values to send a transactional email
        $sendSmtpEmail = new \SendinBlue\Client\Model\SendSmtpEmail();
        $sendSmtpEmail->setSubject("Password reset for carrental.ipd23.com");
        $sendSmtpEmail->setSender(new \SendinBlue\Client\Model\SendSmtpEmailSender(
            ['name' => 'No-Reply', 'email' => 'noreply@carrental.ipd23.com']) );
        $sendSmtpEmail->setTo([ new \SendinBlue\Client\Model\SendSmtpEmailTo(
            ['name' => $user['name'], 'email' => $email])  ]);
        $sendSmtpEmail->setHtmlContent($emailBody);
        //
        try {
            $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
            $log->debug(sprintf("Password reset sent to %s", $email));
            return $this->view->render($response, 'password_reset_sent.html.twig');
        } catch (Exception $e) {
            $log->error(sprintf("Error sending password reset email to %s\n:%s", $email, $e->getMessage()));
            return $response->withHeader("Location", "/error_internal",403);            
        }
        // end of option 2 code
    }
    //
    return $this->view->render($response, 'password_reset_sent.html.twig');
});

$app->map(['GET', 'POST'], '/passresetaction/{secret}', function (Request $request, Response $response, array $args) {
    global $log;
 //   $view = Twig::fromRequest($request);
    // this needs to be done both for get and post
    $secret = $args['secret'];
    $resetRecord = DB::queryFirstRow("SELECT * FROM passwordresets WHERE secret=%s", $secret);
    if (!$resetRecord) {
        $log->debug(sprintf('password reset token not found, token=%s', $secret));
        return $this->view->render($response, 'password_reset_action_notfound.html.twig');
    }
    // check if password reset has not expired
    $creationDT = strtotime($resetRecord['creationDateTime']); // convert to seconds since Jan 1, 1970 (UNIX time)
    $nowDT = strtotime(gmdate("Y-m-d H:i:s")); // current time GMT
    if ($nowDT - $creationDT > 60*60) { // expired
        DB::delete('passwordresets', 'secret=%s', $secret);
        $log->debug(sprintf('password reset token expired userid=%s, token=%s', $resetRecord['userId'], $secret));
        return $this->view->render($response, 'password_reset_action_notfound.html.twig');
    }
    // 
    if ($request->getMethod() == 'POST') {
        $post = $request->getParsedBody();
        $pass1 = $post['pass1'];
        $pass2 = $post['pass2'];
        $errorList = array();
        if ($pass1 != $pass2) {
            array_push($errorList, "Passwords don't match");
        } else {
            $passQuality = verifyPasswordQuality($pass1);
            if ($passQuality !== TRUE) {
                array_push($errorList, $passQuality);
            }
        }
        //
        if ($errorList) {
            return $view->render($response, 'password_reset_action.html.twig', ['errorList' => $errorList]);
        } else {
            DB::update('users', ['password' => $pass1], "id=%d", $resetRecord['userId']);
            DB::delete('passwordresets', 'secret=%s', $secret); // cleanup the record
            return $view->render($response, 'password_reset_action_success.html.twig');
        }
    } else {
        return $view->render($response, 'password_reset_action.html.twig');
    }
});



function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}*/
//Admin