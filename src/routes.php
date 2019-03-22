<?php

use Slim\Http\Request;
use Slim\Http\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Routes

$app->get('/', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");
    // Render index view
    return $this->renderer->render($response, 'home.phtml', $args);
});
$app->get('/master', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->any('/api/admin/login', function (Request $request, Response $response, array $args) {
    if ($request->getMethod() == "OPTIONS") {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    }
    $input = (array)$request->getParsedBody();
    $this->logger->info("Admin_Login_Attempt " . time() . " " . $input);
    if (isset($input['loginhash'])) {
        $q = $this->db->prepare('SELECT admin_username as username, admin_password as password, display_pic as dp, admin_name as name FROM admins WHERE admin_logintoken=:logintoken');
        $q->execute(array('logintoken' => $input['loginhash']));
        $r = $q->fetch(PDO::FETCH_OBJ);
        $pass = $r->password;
    } else {
        $q = $this->db->prepare('SELECT admin_username as username, admin_password as password, display_pic as dp, admin_name as name FROM admins WHERE admin_username=:username');
        $q->execute(array('username' => $input['username']));
        $r = $q->fetch(PDO::FETCH_OBJ);
        $pass = trim($input['password']);
    }
    if (isset($r->username)) {
        // User found, continue checking
        if ($r->password == $pass) {
            // User found, password matched
            if (isset($input['loginhash'])) {
                $loginhash = $input['loginhash'];
            } else {
                $loginhash = hash("sha256", $r->username . $r->password . time());
                $u = $this->db->prepare('UPDATE admins SET admin_logintoken=:loginhash WHERE admin_username=:username');
                $u->execute(array('username' => $input['username'], 'loginhash' => $loginhash));
            }
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withJson(array('success' => 1, 'name' => $r->name, 'dp' => $r->dp, 'token' => $loginhash));
        }
        // User found, password did not match
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withJson(array('success' => 0, 'reason' => 'Invalid Password'));
    }
    // User not found
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withJson(array('success' => 0, 'reason' => 'User not found'));
});

$app->any('/api/admin/feature/add', function (Request $request, Response $response, array $args) {
    if ($request->getMethod() == "OPTIONS") {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    }
    $input = (array)$request->getParsedBody();
    $directory = $this->get('upload_directory');
    if(isset($input['feature_image']) && !empty($input['feature_image'])) {
        $input['feature_image'] = saveB64File($directory, $input['feature_image']);
    }
    $q = $this->db->prepare('INSERT INTO featureslist(feature_name, feature_image) VALUES (:feature_name, :feature_image)');

    $q->execute(array(
        'feature_name' => $input['feature_name'],
        'feature_image' => $input['feature_image']
    ));
    $qe = $this->db->prepare('SELECT * FROM featureslist');
    $qe->execute();
    $re = $qe->fetchAll();
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withJson(array('success' => 1, 'reason' => 'Added Successfully', 'features' => $re));
});
$app->any('/api/admin/feature/remove', function (Request $request, Response $response, array $args) {
    if ($request->getMethod() == "OPTIONS") {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    }
    $input = (array)$request->getParsedBody();
    $q = $this->db->prepare('DELETE FROM featureslist WHERE feature_id=:feature_id');
    $q->execute(array(
        'feature_id' => $input['feature_id'],
    ));
    $qe = $this->db->prepare('SELECT * FROM featureslist');
    $qe->execute();
    $re = $qe->fetchAll();
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withJson(array('success' => 1, 'reason' => 'Deleted Successfully', 'features' => $re));
});
$app->any('/api/admin/feature/list', function (Request $request, Response $response, array $args) {
    if ($request->getMethod() == "OPTIONS") {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    }
    $q = $this->db->prepare('SELECT * FROM featureslist');
    $q->execute();
    $r = $q->fetchAll();

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withJson(array('success' => 1, 'features' => $r));
});

$app->any('/api/admin/menu/add', function (Request $request, Response $response, array $args) {
    if ($request->getMethod() == "OPTIONS") {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    }
    $input = (array)$request->getParsedBody();
    $directory = $this->get('upload_directory');
    if(isset($input['menu_image']) && !empty($input['menu_image'])) {
        $input['menu_image'] = saveB64File($directory, $input['menu_image']);
    }

    $q = $this->db->prepare('INSERT INTO menulist(menu_name, menu_image) VALUES (:menu_name, :menu_image)');
    $q->execute(array(
        'menu_name' => $input['menu_name'],
        'menu_image' => $input['menu_image']
    ));

    $qe = $this->db->prepare('SELECT * FROM menulist');
    $qe->execute();
    $re = $qe->fetchAll();
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withJson(array('success' => 1, 'reason' => 'Added Successfully', 'menus' => $re));
});
$app->any('/api/admin/menu/remove', function (Request $request, Response $response, array $args) {
    if ($request->getMethod() == "OPTIONS") {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    }
    $input = (array)$request->getParsedBody();
    $q = $this->db->prepare('DELETE FROM menulist WHERE menu_id=:menu_id');
    $q->execute(array(
        'menu_id' => $input['menu_id'],
    ));
    $qe = $this->db->prepare('SELECT * FROM menulist');
    $qe->execute();
    $re = $qe->fetchAll();
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withJson(array('success' => 1, 'reason' => 'Deleted Successfully', 'menus' => $re));
});
$app->any('/api/admin/menu/list', function (Request $request, Response $response, array $args) {
    if ($request->getMethod() == "OPTIONS") {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    }
    $q = $this->db->prepare('SELECT * FROM menulist');
    $q->execute();
    $r = $q->fetchAll();

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withJson(array('success' => 1, 'menus' => $r));
});

$app->any('/api/admin/bar/add', function (Request $request, Response $response, array $args) {
    if ($request->getMethod() == "OPTIONS") {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    }
    $input = (array)$request->getParsedBody();
    $directory = $this->get('upload_directory');
    if(isset($input['bar_image_one']) && !empty($input['bar_image_one'])) {
        $input['bar_image_one'] = saveB64File($directory, $input['bar_image_one']);
    }
    if(isset($input['bar_image_two']) && !empty($input['bar_image_two'])) {
        $input['bar_image_two'] = saveB64File($directory, $input['bar_image_two']);
    }
    if(isset($input['bar_image_three']) && !empty($input['bar_image_three'])) {
        $input['bar_image_three'] = saveB64File($directory, $input['bar_image_three']);
    }
    if(isset($input['bar_image_four']) && !empty($input['bar_image_four'])) {
        $input['bar_image_four'] = saveB64File($directory, $input['bar_image_four']);
    }

    $q = $this->db->prepare('INSERT INTO barlist(bar_name, bar_description, bar_image_one, bar_image_two, bar_image_three, bar_image_four) VALUES (:bar_name, :bar_description, :bar_image_one, :bar_image_two, :bar_image_three, :bar_image_four)');
    $q->execute(array(
        'bar_name' => $input['bar_name'],
        'bar_description' => $input['bar_description'],
        'bar_image_one' => $input['bar_image_one'],
        'bar_image_two' => $input['bar_image_two'],
        'bar_image_three' => $input['bar_image_three'],
        'bar_image_four' => $input['bar_image_four']
    ));
    $lid = $this->db->lastInsertId();

    foreach ($input['bar_features'] as $feat) {
        $q2 = $this->db->prepare('INSERT INTO barfeaturelist(bar_id, feature_id) VALUES (:bar_id, :feature_id)');
        $q2->execute(array(
            'bar_id' => $lid,
            'feature_id' => $feat
        ));
    }

    foreach ($input['bar_menus'] as $menu) {
        $q2 = $this->db->prepare('INSERT INTO barmenulist(bar_id, menu_id, menu_price) VALUES (:bar_id, :menu_id, :menu_price)');
        $q2->execute(array(
            'bar_id' => $lid,
            'menu_id' => $menu['menu_id'],
            'menu_price' => $menu['menu_price']
        ));
    }

    $qe = $this->db->prepare('SELECT * FROM barlist');
    $qe->execute();
    $re = $qe->fetchAll();

    foreach ($re as &$item) {
        $qq = $this->db->prepare('SELECT feature_id FROM barfeaturelist WHERE bar_id=:bar_id');
        $qq->execute(array(
            'bar_id' => $item->bar_id
        ));
        $rr = $qq->fetchAll(PDO::FETCH_COLUMN);

        $qqq = $this->db->prepare('SELECT m.*, b.menu_price FROM barmenulist b, menulist m WHERE b.bar_id=:bar_id AND b.menu_id=m.menu_id');
        $qqq->execute(array(
            'bar_id' => $item->bar_id
        ));
        $rrr = $qqq->fetchAll();

        $item['bar_features'] = $rr;
        $item['bar_menus'] = $rrr;
    }

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withJson(array('success' => 1, 'reason' => 'Added Successfully', 'bars' => $re, 'input' => $input));
});
$app->any('/api/admin/bar/remove', function (Request $request, Response $response, array $args) {
    if ($request->getMethod() == "OPTIONS") {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    }
    $input = (array)$request->getParsedBody();
    $q = $this->db->prepare('DELETE FROM barlist WHERE bar_id=:bar_id');
    $q->execute(array(
        'bar_id' => $input['bar_id'],
    ));
    $qe = $this->db->prepare('SELECT * FROM barlist');
    $qe->execute();
    $re = $qe->fetchAll();
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withJson(array('success' => 1, 'reason' => 'Deleted Successfully', 'bars' => $re));
});
$app->any('/api/admin/bar/list', function (Request $request, Response $response, array $args) {
    if ($request->getMethod() == "OPTIONS") {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    }
    $q = $this->db->prepare('SELECT * FROM barlist');
    $q->execute();
    $r = $q->fetchAll();

    $qe = $this->db->prepare('SELECT * FROM menulist');
    $qe->execute();
    $re = $qe->fetchAll();

    $qr = $this->db->prepare('SELECT * FROM featureslist');
    $qr->execute();
    $rr = $qr->fetchAll();

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withJson(array('success' => 1, 'bars' => $r, 'menus' => $re, 'features' => $rr));
});

function saveB64File($directory, $b64String)
{
    $fileNameWithoutExtention = md5($b64String);
    $tmp = explode(',', $b64String);
    $tmp2 = explode('/', $tmp[0]); // data:image gif;base64
    $tmp3 = explode(';', $tmp2[1]); // gif base64
    $data = base64_decode($tmp[1]);
    $ext = $tmp3[0];
    if($ext == 'jpeg') { $ext = 'jpg'; }
    file_put_contents($directory . DIRECTORY_SEPARATOR . $fileNameWithoutExtention . "." . $ext, $data);
    return $fileNameWithoutExtention . "." . $ext;
}
function sendTheMail ($name, $email, $subject, $comment) {
    $mail = new PHPMailer(true);                    // Passing `true` enables exceptions
    $status = '';
    try {
        //Server settings
        $mail->SMTPDebug = 0;                                 // Enable verbose debug output
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = 'smtp.hostinger.in';                    // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = 'noreply@iruseyewear.com';          // SMTP username
        $mail->Password = '7s4VzFvSW1ux';                     // SMTP password
        $mail->SMTPSecure = 'ssl';                            // Enable TLS encryption, `ssl` also accepted
        $mail->Port = 587;                                    // TCP port to connect to

        //Recipients
        $mail->setFrom('noreply@iruseyewear.com', 'IRUS Website');
        $mail->addAddress('info@ronakoptik.com', 'IRUS Admin');     // Add a recipient
        $mail->addReplyTo($email, $name);
        $mail->addBCC('sbongoog@gmail.com');

        // Attachments
        // $mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
        // $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

        //Content
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = "Sent from website : $subject";
        $mail->Body    = '<table border="0" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-family: \'Roboto\', sans-serif">
    <tr>
        <td bgcolor="#2372ba" height="120"> </td>
        <td bgcolor="#2372ba" style="width: 520px; max-width: 100%" rowspan="2" valign="top">
            <img src="http://iruseyewear.com/img/1_logo_top.png" alt="" style="display: block; padding: 20px 60px; box-sizing: border-box; width: 100%">
            <table border="0" width="100%" cellpadding="20" cellspacing="0" bgcolor="#f6ffff" style="border-collapse: collapse; border-radius: 5px 5px 0 0; border-color: transparent">
                <tr>
                    <td>
                        <h1 style="display: block; padding: 30px 0; text-align: center">Hi !</h1>
                        <p style="font-size: 18px; text-align: justify">'.$name.' sent a message via the contact form on website.</p>
                        <table border="0" width="70%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; font-size: 16px; margin: 10px auto">
                            <tr>
                                <td>Name</td>
                                <td style="text-align: right">'.$name.'</td>
                            </tr>
                            <tr>
                                <td>Email</td>
                                <td style="text-align: right">'.$email.'</td>
                            </tr>
                            <tr>
                                <td>Subject</td>
                                <td style="text-align: right">'.$subject.'</td>
                            </tr>
                            <tr>
                                <td>Comment</td>
                                <td style="text-align: right">'.$comment.'</td>
                            </tr>
                        </table>
                        <p style="font-size: 18px">Thank you.<br>Team Artific</p>
                    </td>
                </tr>
            </table>
        </td>
        <td bgcolor="#2372ba" height="220"> </td>
    </tr>
    <tr>
        <td bgcolor="#f4f5f6">&nbsp;</td>
        <td bgcolor="#f4f5f6">&nbsp;</td>
    </tr>
    <tr>
        <td bgcolor="#f4f5f6">&nbsp;</td>
        <td bgcolor="#f4f5f6" style="padding-top: 40px; padding-bottom: 40px; font-size: 12px">
            You have received this mail because you have been set as Admin with IRUS EYEWEAR.
        </td>
        <td bgcolor="#f4f5f6">&nbsp;</td>
    </tr>
</table>';
        $mail->AltBody = 'Please check this mail out using html enabled mail client.';

        $mail->send();
    } catch (Exception $e) {}
}
