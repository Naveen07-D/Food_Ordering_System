<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$allowedOrigins = [
  'http://localhost:3000',
  'http://localhost:3001',
  'http://localhost:3002',
  'https://zoros.co.in'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Credentials: true");
} else {
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Credentials: true");
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

if (php_sapi_name() !== 'cli-server') {
  if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
      header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
      exit();
    }
  }
}
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

$baseDir           = dirname(__DIR__);
$dataDir           = "$baseDir/public/data";
$ordersFile        = "$dataDir/orders.json";
$subscriptionsFile = "$dataDir/subscriptions.json";
$storeStatusFile   = "$dataDir/store_status.json";
$usersFile         = "$dataDir/users.json";
$menuFile          = "$dataDir/menu.json";
$otpsFile          = "$dataDir/otps.json";
$customInvoicesFile = "$dataDir/custom_invoices.json";

if (!file_exists($dataDir)) {
  mkdir($dataDir, 0755, true);
}
if (!file_exists($ordersFile)) {
  file_put_contents($ordersFile, json_encode([]));
}
if (!file_exists($subscriptionsFile)) {
  file_put_contents($subscriptionsFile, json_encode([]));
}
if (!file_exists($storeStatusFile)) {
  file_put_contents($storeStatusFile, json_encode([
    'isOpen'  => true,
    'message' => 'We are open now!'
  ], JSON_PRETTY_PRINT));
}
if (!file_exists($usersFile)) {
  file_put_contents($usersFile, json_encode([]));
}
if (!file_exists($menuFile)) {
  $initialMenu = [
    [
      "category" => "Main Dishes",
      "items"    => [
        [ "id" => 1, "name" => "Shawarma with Salad",     "price" => 149, "available" => true, "hidden" => false ],
        [ "id" => 2, "name" => "Shawarma without Salad",  "price" => 149, "available" => true, "hidden" => false ]
      ]
    ],
    [
      "category" => "Fries & Sides",
      "items"    => [
        [ "id" => 3, "name" => "French Fries",    "price" => 79,  "available" => true, "hidden" => false ],
        [ "id" => 4, "name" => "Chicken Nuggets", "price" => 99,  "available" => true, "hidden" => false ]
      ]
    ]
  ];
  file_put_contents($menuFile, json_encode($initialMenu, JSON_PRETTY_PRINT));
}
if (!file_exists($otpsFile)) {
  file_put_contents($otpsFile, json_encode(new stdClass()));
}
if (!file_exists($customInvoicesFile)) {
  file_put_contents($customInvoicesFile, json_encode([]));
}

function loadData($file) {
  return json_decode(file_get_contents($file), true);
}
function saveData($file, $data) {
  file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}
function findUserByMobile($m) {
  global $usersFile;
  $users = loadData($usersFile);
  foreach ($users as $user) {
    if ($user['mobile'] === $m) return $user;
  }
  return null;
}
function findUserByEmail($e) {
  global $usersFile;
  $users = loadData($usersFile);
  foreach ($users as $user) {
    if ($user['email'] === $e) return $user;
  }
  return null;
}
function hashPassword($pw) {
  return password_hash($pw, PASSWORD_DEFAULT);
}
function verifyPassword($pw, $hash) {
  return password_verify($pw, $hash);
}
function generateReferralCode($name, $mobile) {
  $clean = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($name));
  return substr($clean, 0, 3) . substr($mobile, -4);
}
function generateOrderId($prefix = 'ORD') {
  return $prefix . '-' . strtoupper(uniqid());
}

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/api/menu' && $method === 'GET') {
  echo json_encode([
    'storeStatus' => loadData($GLOBALS['storeStatusFile']),
    'menu'        => loadData($GLOBALS['menuFile'])
  ]);
  exit();
}

if ($uri === '/api/register' && $method === 'POST') {
  $input    = json_decode(file_get_contents('php://input'), true);
  $name     = trim($input['name']    ?? '');
  $mobile   = trim($input['mobile']  ?? '');
  $email    = trim($input['email']   ?? '');
  $password =        $input['password'] ?? '';

  // Remove email validation and requirement for registration
  if (!$name || !preg_match('/^[0-9]{10}$/', $mobile) || strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Name, 10-digit mobile, and password ≥6 chars required.']);
    exit();
  }

  $users = loadData($GLOBALS['usersFile']);
  foreach ($users as $u) {
    if ($u['mobile'] === $mobile) {
      echo json_encode(['success' => false, 'message' => 'Mobile already registered.']);
      exit();
    }
  }
  $hashed       = hashPassword($password);
  $referralCode = generateReferralCode($name, $mobile);
  $newUser = [
    'id'           => uniqid(),
    'name'         => $name,
    'mobile'       => $mobile,
    'email'        => $email,
    'password'     => $hashed,
    'referralCode' => $referralCode,
    'createdAt'    => date('Y-m-d H:i:s')
  ];
  $users[] = $newUser;
  saveData($GLOBALS['usersFile'], $users);
  echo json_encode([
    'success' => true,
    'message' => 'Registration successful',
    'user'    => [
      'name'         => $name,
      'mobile'       => $mobile,
      'email'        => $email,
      'referralCode' => $referralCode
    ]
  ]);
  exit();
}

if ($uri === '/api/login' && $method === 'POST') {
  $input   = json_decode(file_get_contents('php://input'), true);
  $mobile  = trim($input['mobile']   ?? '');
  $password=        $input['password'] ?? '';
  if (!preg_match('/^[0-9]{10}$/', $mobile)) {
    echo json_encode(['success' => false, 'message' => 'Invalid mobile number']);
    exit();
  }
  if ($mobile === '8919791855' && $password === 'admin123') {
    $_SESSION['user'] = [
      'name'         => 'Admin',
      'mobile'       => '8919791855',
      'referralCode' => 'ADMIN'
    ];
    echo json_encode([
      'success' => true,
      'message' => 'Admin login successful',
      'user'    => $_SESSION['user']
    ]);
    exit();
  }
  $user = findUserByMobile($mobile);
  if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit();
  }
  if (verifyPassword($password, $user['password'])) {
    $_SESSION['user'] = $user;
    echo json_encode(['success' => true, 'message' => 'Login successful', 'user' => $user]);
    exit();
  }
  echo json_encode(['success' => false, 'message' => 'Incorrect password']);
  exit();
}

if ($uri === '/api/forgot-password/send-otp' && $method === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  $email = trim($input['email'] ?? '');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit();
  }
  $user = findUserByEmail($email);
  if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Email not registered']);
    exit();
  }
  $otp = rand(100000, 999999);
  $subject = 'Your Password Reset OTP';
  $body = "Hello {$user['name']},\n\nYour OTP for password reset is: $otp\nIt will expire in 5 minutes.\n\nIf you didn’t request this, please ignore.";

  $mailFrom = $_ENV['MAIL_FROM'] ?? null;
  if (!$mailFrom) {
    echo json_encode(['success' => false, 'message' => 'MAIL_FROM not configured']);
    exit();
  }

  $headers = "From: $mailFrom\r\n";
  if (!mail($email, $subject, $body, $headers)) {
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP email']);
    exit();
  }

  $otps = loadData($GLOBALS['otpsFile']);
  $otps[$email] = ['otp' => $otp, 'expires' => time() + 300];
  saveData($GLOBALS['otpsFile'], $otps);

  echo json_encode(['success' => true, 'message' => 'OTP sent to email']);
  exit();
}

if ($uri === '/api/forgot-password/reset' && $method === 'POST') {
  $input       = json_decode(file_get_contents('php://input'), true);
  $email       = trim($input['email']       ?? '');
  $otp         =        $input['otp']         ?? '';
  $newPassword =        $input['newPassword'] ?? '';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{6}$/', $otp) || strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
  }
  $otps = loadData($GLOBALS['otpsFile']);
  if (!isset($otps[$email]) || $otps[$email]['otp'] !== (int)$otp || $otps[$email]['expires'] < time()) {
    echo json_encode(['success' => false, 'message' => 'OTP invalid or expired']);
    exit();
  }
  $users   = loadData($GLOBALS['usersFile']);
  $updated = false;
  foreach ($users as &$u) {
    if ($u['email'] === $email) {
      $u['password'] = hashPassword($newPassword);
      $updated = true;
      break;
    }
  }
  if ($updated) {
    saveData($GLOBALS['usersFile'], $users);
    unset($otps[$email]);
    saveData($GLOBALS['otpsFile'], $otps);
    echo json_encode(['success' => true, 'message' => 'Password reset successful']);
  } else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
  }
  exit();
}

if ($uri === '/api/store-status' && $method === 'GET') {
  echo file_get_contents($GLOBALS['storeStatusFile']);
  exit();
}
if ($uri === '/api/store-status' && $method === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  saveData($GLOBALS['storeStatusFile'], $input);
  echo json_encode(['success' => true]);
  exit();
}

if ($uri === '/api/orders/store' && $method === 'GET') {
  echo file_get_contents($GLOBALS['ordersFile']);
  exit();
}
if ($uri === '/api/orders/store' && $method === 'POST') {
  $input  = json_decode(file_get_contents('php://input'), true);
  $orders = loadData($GLOBALS['ordersFile']);
  $newOrder = [
    'id'           => uniqid(),
    'userId'       => $input['userId']   ?? 'guest',
    'items'        => $input['items'],
    'total'        => $input['total'],
    'date'         => $input['date'],
    'status'       => $input['status'],
    'mobileNumber' => $input['mobileNumber'],
    'customerName' => $input['customerName'],
    'orderId'      => $input['orderId']  ?? generateOrderId()
  ];
  $orders[] = $newOrder;
  saveData($GLOBALS['ordersFile'], $orders);
  echo json_encode($newOrder);
  exit();
}
if (preg_match('/\/api\/orders\/store\/([^\/]+)\/status/', $uri, $m) && $method === 'PUT') {
  $orderId = $m[1];
  $input   = json_decode(file_get_contents('php://input'), true);
  $orders  = loadData($GLOBALS['ordersFile']);
  foreach ($orders as &$o) {
    if ($o['id'] === $orderId) {
      $o['status'] = $input['status'];
      break;
    }
  }
  saveData($GLOBALS['ordersFile'], $orders);
  echo json_encode(['success' => true]);
  exit();
}

if ($uri === '/api/orders/subscriptions' && $method === 'GET') {
  echo file_get_contents($GLOBALS['subscriptionsFile']);
  exit();
}
if ($uri === '/api/orders/subscriptions' && $method === 'POST') {
  $input         = json_decode(file_get_contents('php://input'), true);
  $subs           = loadData($GLOBALS['subscriptionsFile']);
  $newSubscription = [
    'id'              => uniqid(),
    'userId'          => $input['userId'],
    'userName'        => $input['userName'],
    'planType'        => $input['planType'],
    'frequency'       => $input['frequency'],
    'fruitsIncluded'  => $input['fruitsIncluded'],
    'cost'            => $input['cost'],
    'startDate'       => $input['startDate'],
    'endDate'         => $input['endDate'],
    'paymentVerified' => false,
    'subscriptionId'  => $input['subscriptionId'] ?? generateOrderId('SUB'),
    'referralCode'    => $input['referralCode']    ?? null,
    'address'         => $input['address'],
    'email'           => $input['email'],
    'mobileNumber'    => $input['mobileNumber'],
    'createdAt'       => date('Y-m-d H:i:s')
  ];
  $subs[] = $newSubscription;
  saveData($GLOBALS['subscriptionsFile'], $subs);
  echo json_encode($newSubscription);
  exit();
}
if (preg_match('/\/api\/orders\/subscriptions\/([^\/]+)\/confirm/', $uri, $m2) && $method === 'PUT') {
  $subId = $m2[1];
  $subs  = loadData($GLOBALS['subscriptionsFile']);
  foreach ($subs as &$s) {
    if ($s['id'] === $subId) {
      $s['paymentVerified'] = true;
      $s['verifiedAt']      = date('Y-m-d H:i:s');
      break;
    }
  }
  saveData($GLOBALS['subscriptionsFile'], $subs);
  echo json_encode(['success' => true]);
  exit();
}

// New endpoint to reject subscription
if (preg_match('/\/api\/orders\/subscriptions\/([^\/]+)\/reject/', $uri, $m3) && $method === 'PUT') {
  $subId = $m3[1];
  $subs  = loadData($GLOBALS['subscriptionsFile']);
  foreach ($subs as &$s) {
    if ($s['id'] === $subId) {
      $s['status'] = 'rejected';
      $s['rejectedAt'] = date('Y-m-d H:i:s');
      break;
    }
  }
  saveData($GLOBALS['subscriptionsFile'], $subs);
  echo json_encode(['success' => true]);
  exit();
}

if ($uri === '/api/admin/data' && $method === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  $filterType = $input['filterType'] ?? 'today';
  $filterValue = $input['filterValue'] ?? null;

  // Load all data files
  $orders = loadData($GLOBALS['ordersFile']);
  $subscriptions = loadData($GLOBALS['subscriptionsFile']);
  $customInvoices = loadData($GLOBALS['customInvoicesFile']);
  $menu = loadData($GLOBALS['menuFile']);

  // Helper function to filter by date
  function filterByDate($dateStr, $filterType, $filterValue) {
    $date = new DateTime($dateStr);
    $now = new DateTime();
    switch ($filterType) {
      case 'today':
        return $date->format('Y-m-d') === $now->format('Y-m-d');
      case 'yesterday':
        $yesterday = (clone $now)->modify('-1 day');
        return $date->format('Y-m-d') === $yesterday->format('Y-m-d');
      case 'this_week':
        $weekStart = (clone $now)->modify('monday this week')->format('W');
        return $date->format('W') === $weekStart && $date->format('Y') === $now->format('Y');
      case 'this_month':
        return $date->format('Y-m') === $now->format('Y-m');
      case 'custom_date':
        return $date->format('Y-m-d') === $filterValue;
      case 'custom_month':
        return $date->format('Y-m') === $filterValue;
      case 'custom_year':
        return $date->format('Y') === $filterValue;
      default:
        return false;
    }
  }

  // Filter orders
  $filteredOrders = array_filter($orders, function($o) use ($filterType, $filterValue) {
    return filterByDate($o['date'], $filterType, $filterValue);
  });

  // Filter subscriptions by createdAt
  $filteredSubscriptions = array_filter($subscriptions, function($s) use ($filterType, $filterValue) {
    return filterByDate($s['createdAt'], $filterType, $filterValue);
  });

  // Filter custom invoices by date
  $filteredInvoices = array_filter($customInvoices, function($inv) use ($filterType, $filterValue) {
    return filterByDate($inv['date'], $filterType, $filterValue);
  });

  // Calculate number of items sold in filtered orders
  $itemsSold = 0;
  $menuItemsSoldMap = [];
  foreach ($filteredOrders as $order) {
    $items = json_decode($order['items'], true);
    foreach ($items as $item) {
      $itemsSold += $item['quantity'];
      if (!isset($menuItemsSoldMap[$item['name']])) {
        $menuItemsSoldMap[$item['name']] = 0;
      }
      $menuItemsSoldMap[$item['name']] += $item['quantity'];
    }
  }
  $menuItemsSold = [];
  foreach ($menuItemsSoldMap as $name => $quantity) {
    $menuItemsSold[] = ['name' => $name, 'piecesSold' => $quantity];
  }

  // Calculate number of custom invoices and aggregate items sold
  $customInvoicesCount = count($filteredInvoices);
  $customInvoicesSoldMap = [];
  foreach ($filteredInvoices as $invoice) {
    $items = $invoice['items'];
    if (is_string($items)) {
      $items = json_decode($items, true);
    }
    foreach ($items as $item) {
      if (!isset($customInvoicesSoldMap[$item['name']])) {
        $customInvoicesSoldMap[$item['name']] = 0;
      }
      $customInvoicesSoldMap[$item['name']] += $item['quantity'];
    }
  }
  $customInvoicesSold = [];
  foreach ($customInvoicesSoldMap as $name => $quantity) {
    $customInvoicesSold[] = ['name' => $name, 'piecesSold' => $quantity];
  }

  // Calculate number of subscriptions and aggregate items sold
  $subscriptionsCount = count($filteredSubscriptions);
  $subscriptionOrdersSoldMap = [];
  foreach ($filteredSubscriptions as $sub) {
    if (isset($sub['items'])) {
      foreach ($sub['items'] as $item) {
        if (!isset($subscriptionOrdersSoldMap[$item['name']])) {
          $subscriptionOrdersSoldMap[$item['name']] = 0;
        }
        $subscriptionOrdersSoldMap[$item['name']] += $item['quantity'];
      }
    }
  }
  $subscriptionOrdersSold = [];
  foreach ($subscriptionOrdersSoldMap as $name => $quantity) {
    $subscriptionOrdersSold[] = ['name' => $name, 'piecesSold' => $quantity];
  }

  // Calculate number of menu orders (count of filtered orders)
  $menuOrdersCount = count($filteredOrders);

  $result = [
    'itemsSold' => $itemsSold,
    'menuOrdersCount' => $menuOrdersCount,
    'customInvoicesCount' => $customInvoicesCount,
    'subscriptionsCount' => $subscriptionsCount,
    'filteredOrders' => array_values($filteredOrders),
    'filteredSubscriptions' => array_values($filteredSubscriptions),
    'filteredInvoices' => array_values($filteredInvoices),
    'menuItemsSold' => $menuItemsSold,
    'customInvoicesSold' => $customInvoicesSold,
    'subscriptionOrdersSold' => $subscriptionOrdersSold
  ];

  echo json_encode($result);
  exit();
}

if (preg_match('~/api/menu/item/(\d+)/availability~', $uri, $m) && $method === 'PUT') {
  $itemId = (int)$m[1];
  $input  = json_decode(file_get_contents('php://input'), true);
  $available = $input['available'];
  $menuArr = loadData($GLOBALS['menuFile']);
  foreach ($menuArr as &$cat) {
    foreach ($cat['items'] as &$item) {
      if ($item['id'] === $itemId) {
        $item['available'] = $available;
      }
    }
  }
  saveData($GLOBALS['menuFile'], $menuArr);
  echo json_encode(['success' => true]);
  exit();
}

if (strpos($uri, '/api/custom-invoices') === 0) {
  $invoices = loadData($GLOBALS['customInvoicesFile']);
  if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $invoices[] = $input;
    saveData($GLOBALS['customInvoicesFile'], $invoices);
    echo json_encode(['success' => true, 'invoice' => $input]);
    exit();
  }
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
exit();
?>
