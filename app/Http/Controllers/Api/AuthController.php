<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRole;
use App\Services\EcrService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private function out($data, int $code, string $message)
    {
        return response()->json([
            'data'    => $data,
            'code'    => $code,
            'message' => $message,
        ]);
    }

    // =========================================================================
    // LOGIN — LDAP only (IS_LDAP = 1 always)
    // login() / changePassword() removed — dead code, used md5 hashing.
    // All auth goes through loginLdap(). If non-LDAP ever needed, implement
    // with Hash::make() / Hash::check() — never md5.
    // =========================================================================

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->out(null, 1, 'Logged out successfully.');
    }

    public function me(Request $request)
    {
        $user  = $request->user();
        $roles = UserRole::where('user_id', $user->id)->pluck('role_id')->toArray();

        // Resolve human-readable names from ECR HRMS
        $ecr             = new EcrService();
        $designationName = $ecr->getDesignationName($user->emp_designation) ?? $user->emp_designation;
        $departmentName  = $ecr->getDepartmentName($user->emp_department)   ?? $user->emp_department;
        $locationName    = $ecr->getBranchName($user->emp_location)         ?? $user->emp_location;
        $ecr->close();

        return $this->out([
            'id'              => $user->id,
            'emp_code'        => $user->emp_code,
            'name'            => $user->full_name,
            'firstName'       => $user->emp_first_name,
            'middleName'      => $user->emp_middle_name,
            'lastName'        => $user->emp_last_name,
            'email'           => $user->emp_email,
            'phone'           => $user->emp_phone,
            'photo'           => $user->emp_photo,
            'designation'     => $designationName,
            'department'      => $departmentName,
            'emp_designation' => $designationName,
            'emp_department'  => $user->emp_department,
            'doj'             => $user->emp_doj,
            'location'        => $locationName,
            'onRoll'          => $user->on_roll,
            'empStatus'       => $user->emp_status,
            'empActive'       => $user->emp_active,
            'role'            => $roles,
            'client'          => $user->emp_client,
            'clientDept'      => $user->emp_client_department,
        ], 1, 'OK');
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

        $user = User::where('emp_code', $empCode)->first();

        if (!$user) {
            return $this->out(null, 0, 'User not found.');
        }

        if ($user->emp_status === 'D') {
            return $this->out([], 2, 'Account is disabled.');
        }

        // Try each configured LDAP server
        $ldapConfigs = \DB::table('ldap_configs')
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->get();

        if ($ldapConfigs->isEmpty()) {
            return $this->out(null, 0, 'No LDAP server configured.');
        }

        $authenticated = false;
        foreach ($ldapConfigs as $ldap) {
            try {
                $ldapConn = ldap_connect($ldap->host, (int)$ldap->port);
                if (!$ldapConn) continue;

                ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
                ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, 5);

                $bind = @ldap_bind($ldapConn, $empCode . '@' . $ldap->domain, $password);

                if ($bind) {
                    $authenticated = true;
                    ldap_close($ldapConn);
                    break;
                }
                ldap_close($ldapConn);
            } catch (\Exception) {
                continue;
            }
        }

        if (!$authenticated) {
            return $this->out(null, 0, 'Invalid LDAP credentials.');
        }

        // Sync department from ECR if missing
        if ((int)$user->emp_department === 0) {
            $ecr   = new EcrService();
            $deptId = $ecr->getEmployeeDepartmentId($empCode);
            $ecr->close();
            if ($deptId) {
                $user->emp_department = $deptId;
                $user->save();
            }
        }

        $roles = UserRole::where('user_id', $user->id)->pluck('role_id')->toArray();
        if (empty($roles)) {
            $roles = [3]; // default Employee
        }

        $user->last_visited_on = now();
        $user->save();

        $user->tokens()->delete();
        $token = $user->createToken('lms-api')->plainTextToken;

        return $this->out([
            'id'          => $user->id,
            'emp_code'    => $user->emp_code,
            'name'        => $user->full_name,
            'firstName'   => $user->emp_first_name,
            'lastName'    => $user->emp_last_name,
            'email'       => $user->emp_email,
            'phone'       => $user->emp_phone,
            'photo'       => $user->emp_photo,
            'designation' => $user->emp_designation,
            'department'  => $user->emp_department,
            'onRoll'      => $user->on_roll,
            'empStatus'   => $user->emp_status,
            'empActive'   => $user->emp_active,
            'role'        => $roles,
            'token'       => $token,
            'auth_type'   => 'ldap',
        ], 1, 'LDAP Login successful.');
    }
}
