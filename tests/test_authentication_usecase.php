<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../helpers.php';

use FidelsPizza\Application\DTO\AuthenticateUserRequest;
use FidelsPizza\Application\UseCase\AuthenticateUserUseCase;
use FidelsPizza\Domain\Model\AuthenticatedUser;
use FidelsPizza\Domain\Repository\UserRepositoryInterface;

echo "===========================================\n";
echo "AUTHENTICATION USECASE & SECURITY VERIFICATION\n";
echo "===========================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTrue(bool $condition, string $message): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo " [PASS] $message\n";
        $testsPassed++;
    } else {
        echo " [FAIL] $message\n";
        $testsFailed++;
    }
}

// 1. Mock in-memory UserRepository
class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $customers = [];
    /** @var array<string, array<string, mixed>> */
    public array $admins = [];
    /** @var array<array<string, mixed>> */
    public array $sessions = [];

    public function findCustomerByEmail(string $email): ?array
    {
        $key = strtolower(trim($email));
        return $this->customers[$key] ?? null;
    }

    public function findAdminByUsernameOrEmail(string $identifier): ?array
    {
        $key = strtolower(trim($identifier));
        foreach ($this->admins as $admin) {
            if (strtolower($admin['username']) === $key || strtolower($admin['email']) === $key) {
                return $admin;
            }
        }
        return null;
    }

    public function findAdminByEmail(string $email): ?array
    {
        $key = strtolower(trim($email));
        foreach ($this->admins as $admin) {
            if (strtolower($admin['email']) === $key) {
                return $admin;
            }
        }
        return null;
    }

    public function recordSession(string $sessionId, ?int $userId, ?int $adminId, int $ttlSeconds): void
    {
        $this->sessions[] = [
            'id' => $sessionId,
            'user_id' => $userId,
            'admin_id' => $adminId,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ];
    }
}

$mockRepo = new InMemoryUserRepository();
$customerPassword = 'SecretCustomerPassword123!';
$adminPassword = 'SecretAdminPassword123!';

$mockRepo->customers['alice@example.com'] = [
    'id' => 10,
    'first_name' => 'Alice',
    'last_name' => 'Smith',
    'email' => 'alice@example.com',
    'password_hash' => password_hash($customerPassword, PASSWORD_DEFAULT),
    'is_confirmed' => 1,
    'preferred_lang' => 'en',
];

$mockRepo->customers['unconfirmed@example.com'] = [
    'id' => 11,
    'first_name' => 'Bob',
    'last_name' => 'Jones',
    'email' => 'unconfirmed@example.com',
    'password_hash' => password_hash($customerPassword, PASSWORD_DEFAULT),
    'is_confirmed' => 0,
    'preferred_lang' => 'ja',
];

$mockRepo->admins['admin@fidels.com'] = [
    'id' => 1,
    'username' => 'FidelAdmin',
    'email' => 'admin@fidels.com',
    'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
];

$useCase = new AuthenticateUserUseCase($mockRepo);

// Test 1: Empty credentials rejected
$res = $useCase->authenticateCustomer(new AuthenticateUserRequest('', ''));
assertTrue(!$res->success && $res->errorCode === 'error_email_password_required', "Empty email/password correctly rejected");

// Test 2: Invalid email format rejected
$res = $useCase->authenticateCustomer(new AuthenticateUserRequest('not-an-email', 'password'));
assertTrue(!$res->success && $res->errorCode === 'error_invalid_email', "Invalid email format rejected");

// Test 3: Wrong password rejected
$res = $useCase->authenticateCustomer(new AuthenticateUserRequest('alice@example.com', 'wrongpassword'));
assertTrue(!$res->success && $res->errorCode === 'error_invalid_credentials', "Wrong password rejected");

// Test 4: Valid customer credentials succeed
$res = $useCase->authenticateCustomer(new AuthenticateUserRequest('alice@example.com', $customerPassword));
assertTrue($res->success && $res->user !== null, "Valid customer login succeeds");
assertTrue($res->user->isCustomer() === true, "Authenticated user has customer role");
assertTrue($res->user->getId() === 10, "Authenticated user ID is 10");
assertTrue($res->user->getUsernameOrName() === 'Alice', "Customer display name is Alice");
assertTrue($res->user->getPreferredLang() === 'en', "Preferred language is en");

// Test 5: Unconfirmed customer is blocked
$res = $useCase->authenticateCustomer(new AuthenticateUserRequest('unconfirmed@example.com', $customerPassword));
assertTrue(!$res->success && $res->errorCode === 'error_email_confirm_required', "Unconfirmed customer email is blocked from login");

// Test 6: Admin logging in via customer portal fallback succeeds
$res = $useCase->authenticateCustomer(new AuthenticateUserRequest('admin@fidels.com', $adminPassword, allowAdminFallback: true));
assertTrue($res->success && $res->user !== null, "Admin login on customer form succeeds via fallback");
assertTrue($res->user->isAdmin() === true, "Admin role correctly identified");
assertTrue($res->user->getUsernameOrName() === 'FidelAdmin', "Admin username is FidelAdmin");

// Test 7: Admin logging in directly on admin portal
$resAdmin = $useCase->authenticateAdmin('FidelAdmin', $adminPassword);
assertTrue($resAdmin->success && $resAdmin->user !== null, "Admin authenticates by username on admin portal");
assertTrue($resAdmin->user->getId() === 1, "Admin ID is 1");

$resAdminByEmail = $useCase->authenticateAdmin('admin@fidels.com', $adminPassword);
assertTrue($resAdminByEmail->success && $resAdminByEmail->user !== null, "Admin authenticates by email on admin portal");

// Test 8: Admin wrong password rejected
$resAdminBad = $useCase->authenticateAdmin('FidelAdmin', 'badpass');
assertTrue(!$resAdminBad->success && $resAdminBad->errorCode === 'error_invalid_credentials', "Admin wrong password rejected");

// Test 9: Session recording
$sessId = 'test_sess_' . bin2hex(random_bytes(8));
$useCase->createSessionRecord($sessId, $res->user, 3600);
assertTrue(count($mockRepo->sessions) === 1, "Session successfully recorded in repository");
assertTrue($mockRepo->sessions[0]['id'] === $sessId, "Session token matches");
assertTrue($mockRepo->sessions[0]['admin_id'] === 1, "Session admin_id matches");

echo "\n===========================================\n";
echo "SUMMARY: $testsPassed passed, $testsFailed failed\n";
echo "===========================================\n";

if ($testsFailed > 0) {
    exit(1);
}
