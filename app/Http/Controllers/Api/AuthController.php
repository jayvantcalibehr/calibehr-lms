<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private function out($data, int $code, string $message)
    {
        return response()->json([
            'data' => $data,
            'code' => $code,
            'message' => $message,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $empCode = trim($request->input('username'));
        $password = md5($request->input('password'));

        $user = User::where('emp_code', $empCode)->first();

        if (! $user || trim($user->emp_password) !== $password) {
            return $this->out(null, 0, 'Invalid credentials.');
        }

        if ($user->emp_status === 'D') {
            return $this->out([], 2, 'Account is disabled.');
        }

        $roles = UserRole::where('user_id', $user->id)->pluck('role_id')->toArray();
        if (empty($roles)) {
            $roles = [3];
        }

        $user->last_visited_on = now();
        $user->save();

        $user->tokens()->delete();
        $token = $user->createToken('lms-api')->plainTextToken;

        $userData = [
            'id' => $user->id,
            'emp_code' => $user->emp_code,
            'name' => $user->full_name,
            'firstName' => $user->emp_first_name,
            'lastName' => $user->emp_last_name,
            'email' => $user->emp_email,
            'phone' => $user->emp_phone,
            'photo' => $user->emp_photo,
            'designation' => $user->emp_designation,
            'department' => $user->emp_department,
            'onRoll' => $user->on_roll,
            'empStatus' => $user->emp_status,
            'empActive' => $user->emp_active,
            'role' => $roles,
            'token' => $token,
        ];

        return $this->out($userData, 1, 'Login successful.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->out(null, 1, 'Logged out successfully.');
    }

    public function me(Request $request)
    {
        $user  = $request->user();
        $roles = UserRole::where('user_id', $user->id)->pluck('role_id')->toArray();

        // Resolve designation & department names from ECR
        $designationName = $user->emp_designation;
        $departmentName  = $user->emp_department;
        $locationName    = $user->emp_location;

        try {
            $serverName = env('ECR_SQLSRV_HOST', 'tcp:172.16.1.30,1433');
            $config = [
                'Database'               => env('ECR_SQLSRV_DB', 'ECR_New'),
                'Uid'                    => env('ECR_SQLSRV_USER', 'nbg_sa'),
                'PWD'                    => env('ECR_SQLSRV_PASS', ''),
                'TrustServerCertificate' => true,
                'LoginTimeout'           => 5,
            ];
            $conn = @sqlsrv_connect($serverName, $config);
            if ($conn) {
                // Designation name
                if (!empty($user->emp_designation) && is_numeric($user->emp_designation)) {
                    $stmt = sqlsrv_query($conn, "SELECT TOP 1 DesignationName FROM [ECR_New].[dbo].[Designation] WHERE ID = ?", [$user->emp_designation]);
                    if ($stmt) {
                        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                        if ($row) $designationName = $row['DesignationName'];
                    }
                }
                // Department name
                if (!empty($user->emp_department) && is_numeric($user->emp_department) && (int)$user->emp_department > 0) {
                    $stmt = sqlsrv_query($conn, "SELECT TOP 1 DeptName FROM [ECR_New].[dbo].[Department] WHERE ID = ?", [$user->emp_department]);
                    if ($stmt) {
                        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                        if ($row) $departmentName = $row['DeptName'];
                    }
                }
                // Location (Branch) name
                if (!empty($user->emp_location) && is_numeric($user->emp_location) && (int)$user->emp_location > 0) {
                    $stmt = sqlsrv_query($conn, "SELECT TOP 1 BranchName FROM [ECR_New].[dbo].[Branch] WHERE ID = ?", [$user->emp_location]);
                    if ($stmt) {
                        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                        if ($row) $locationName = $row['BranchName'];
                    }
                }
                sqlsrv_close($conn);
            }
        } catch (\Throwable $e) {
            // ECR unreachable — use raw values
        }

        return $this->out([
            'id'          => $user->id,
            'emp_code'    => $user->emp_code,
            'name'        => $user->full_name,
            'firstName'   => $user->emp_first_name,
            'middleName'  => $user->emp_middle_name,
            'lastName'    => $user->emp_last_name,
            'email'       => $user->emp_email,
            'phone'       => $user->emp_phone,
            'photo'       => $user->emp_photo,
            'designation' => $designationName,
            'department'  => $departmentName,
            'emp_designation' => $designationName,
            'emp_department'  => $user->emp_department,
            'doj'         => $user->emp_doj,
            'location'    => $locationName,
            'onRoll'      => $user->on_roll,
            'empStatus'   => $user->emp_status,
            'empActive'   => $user->emp_active,
            'role'        => $roles,
            'client'      => $user->emp_client,
            'clientDept'  => $user->emp_client_department,
        ], 1, 'OK');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'new_password' => 'required|string|min:6'
        ]);

        $user = $request->user();

        // Accept both old_password and current_password field names
        $oldPwd = $request->old_password ?? $request->current_password ?? '';

        if (trim($user->emp_password) !== md5($oldPwd)) {
            return $this->out(null, 0, 'Current password is incorrect.');
        }

        $user->emp_password = md5($request->new_password);
        $user->password_changed = 1;
        $user->save();

        return $this->out(null, 1, 'Password changed successfully.');
    }

    public function accountInfo(Request $request)
    {
        return $this->me($request);
    }

    public function loginLdap(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $empCode = trim($request->input('username'));
        $password = $request->input('password');

        // Database mein user dhundo
        $user = User::where('emp_code', $empCode)->first();

        if (! $user) {
            return $this->out(null, 0, 'User not found.');
        }

        if ($user->emp_status === 'D') {
            return $this->out([], 2, 'Account is disabled.');
        }

        // Active LDAP configs lo
        $ldapConfigs = \DB::table('ldap_configs')
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->get();

        if ($ldapConfigs->isEmpty()) {
            return $this->out(null, 0, 'No LDAP server configured.');
        }

        // Har LDAP server try karo
        $authenticated = false;
        foreach ($ldapConfigs as $ldap) {
            try {
                $ldapConn = ldap_connect($ldap->host, (int) $ldap->port);

                if (! $ldapConn) {
                    continue;
                }

                ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
                ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, 5);

                // Domain\username format
                $ldapUser = $empCode.'@'.$ldap->domain;
                $bind = @ldap_bind($ldapConn, $ldapUser, $password);

                if ($bind) {
                    $authenticated = true;
                    ldap_close($ldapConn);
                    break;
                }

                ldap_close($ldapConn);

            } catch (\Exception $e) {
                continue;
            }
        }

        if (! $authenticated) {
            return $this->out(null, 0, 'Invalid LDAP credentials.');
        }

        // Login successful — sync department from ECR if emp_department is 0
        if ((int)$user->emp_department === 0) {
            try {
                $serverName = env('ECR_SQLSRV_HOST', 'tcp:172.16.1.30,1433');
                $config = [
                    'Database' => env('ECR_SQLSRV_DB', 'ECR_New'),
                    'Uid'      => env('ECR_SQLSRV_USER', 'nbg_sa'),
                    'PWD'      => env('ECR_SQLSRV_PASS', ''),
                    'TrustServerCertificate' => true,
                    'LoginTimeout' => 5,
                ];
                $conn = @sqlsrv_connect($serverName, $config);
                if ($conn) {
                    $sql  = "SELECT TOP 1 DepartmentId FROM [ECR_New].[dbo].[Employee] WHERE EmpCode = ?";
                    $stmt = sqlsrv_query($conn, $sql, [$empCode]);
                    if ($stmt) {
                        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                        if ($row && !empty($row['DepartmentId'])) {
                            $user->emp_department = (int)$row['DepartmentId'];
                            $user->save();
                        }
                    }
                    sqlsrv_close($conn);
                }
            } catch (\Throwable $e) {
                // ECR sync failed — continue login anyway
            }
        }

        $roles = UserRole::where('user_id', $user->id)->pluck('role_id')->toArray();
        if (empty($roles)) {
            $roles = [3];
        }

        $user->last_visited_on = now();
        $user->save();

        $user->tokens()->delete();
        $token = $user->createToken('lms-api')->plainTextToken;

        $userData = [
            'id' => $user->id,
            'emp_code' => $user->emp_code,
            'name' => $user->full_name,
            'firstName' => $user->emp_first_name,
            'lastName' => $user->emp_last_name,
            'email' => $user->emp_email,
            'phone' => $user->emp_phone,
            'photo' => $user->emp_photo,
            'designation' => $user->emp_designation,
            'department' => $user->emp_department,
            'onRoll' => $user->on_roll,
            'empStatus' => $user->emp_status,
            'empActive' => $user->emp_active,
            'role' => $roles,
            'token' => $token,
            'auth_type' => 'ldap',
        ];

        return $this->out($userData, 1, 'LDAP Login successful.');
    }
}