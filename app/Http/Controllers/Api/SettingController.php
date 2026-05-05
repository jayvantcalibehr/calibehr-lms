<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoleMaster;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    private function out($data, int $code, string $msg)
    {
        return response()->json(['data' => $data, 'code' => $code, 'message' => $msg]);
    }

    /**
     * GET /api/Webservices/getAllUserList
     *
     * Optimised for ~1.5 lakh users:
     *   - Server-side pagination, search, filter
     *   - Eager-load roles in 1 query (no N+1)
     *   - 5-min cached aggregate stats
     */
    public function getAllUserList(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $filter = $request->input('filter', 'all');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(200, max(10, (int) $request->input('per_page', 50)));

        $query = User::select(
            'id',
            'emp_first_name', 'emp_middle_name', 'emp_last_name',
            'emp_email', 'emp_code', 'emp_designation',
            'emp_status', 'emp_active', 'emp_add_date', 'emp_photo'
        );

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like) {
                $w->where('emp_code', 'like', $like)
                    ->orWhere('emp_email', 'like', $like)
                    ->orWhere('emp_first_name', 'like', $like)
                    ->orWhere('emp_last_name', 'like', $like);
            });
        }

        if ($filter === 'active') {
            $query->where('emp_active', 'A');
        } elseif ($filter === 'inactive') {
            $query->where('emp_active', '!=', 'A');
        } elseif ($filter === 'unassigned') {
            $query->whereNotIn('id', function ($sub) {
                $sub->select('user_id')->from('user_roles');
            });
        }

        $total = $query->count();
        $pages = (int) ceil($total / $perPage);

        $users = $query->orderBy('emp_first_name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $userIds = $users->pluck('id')->all();
        $rolesByUser = [];
        if (! empty($userIds)) {
            UserRole::whereIn('user_id', $userIds)
                ->get(['user_id', 'role_id'])
                ->groupBy('user_id')
                ->each(function ($group, $uid) use (&$rolesByUser) {
                    $rolesByUser[$uid] = $group->pluck('role_id')
                        ->map(fn ($r) => (int) $r)->values()->all();

                });
        }

        $data = $users->map(function ($u) use ($rolesByUser) {
            $arr = $u->toArray();
            $arr['fullName'] = trim(
                ($u->emp_first_name ?? '').' '.
                ($u->emp_middle_name ?? '').' '.
                ($u->emp_last_name ?? '')
            );
            $arr['roles'] = $rolesByUser[$u->id] ?? [];

            return $arr;
        });

        $stats = Cache::remember('lms.user.stats', 300, function () {
            return [
                'total' => User::count(),
                'active' => User::where('emp_status', 'A')->count(),
                'admins' => UserRole::where('role_id', 1)->distinct('user_id')->count('user_id'),
                'unassigned' => User::whereNotIn('id', function ($sub) {
                    $sub->select('user_id')->from('user_roles');
                })->count(),
            ];
        });

        return $this->out([
            'users' => $data,
            'stats' => $stats,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $pages,
        ], 1, 'OK');
    }

    /** GET /api/Webservices/getUserDetailList?userID=123 */
    public function getUserDetail(Request $request)
    {
        $request->validate(['userID' => 'required|integer']);
        $user = User::findOrFail($request->userID);
        $roles = UserRole::where('user_id', $user->id)->pluck('role_id');

        return $this->out(array_merge($user->toArray(), ['roles' => $roles]), 1, 'OK');
    }

    /** GET /api/Webservices/getAllRoles */
    public function getAllRoles(Request $request)
    {
        $roles = RoleMaster::where('status', 1)->get();

        return $this->out($roles, 1, 'OK');
    }

    /**
     * POST /api/Webservices/setAdminRoles
     * Body: { "userID": 123, "roles": [1, 2] }
     */
    public function setAdminRoles(Request $request)
    {
        $request->validate([
            'userID' => 'required|integer',
            'roles' => 'required|array',
        ]);
        $uid = $request->user()->id;
        $target = $request->userID;

        UserRole::where('user_id', $target)->delete();

        foreach ($request->roles as $roleId) {
            UserRole::create([
                'user_id' => $target,
                'role_id' => $roleId,
                'added_by' => $uid,
                'added_on' => now(),
            ]);
        }

        Cache::forget('lms.user.stats');

        return $this->out(null, 1, 'Roles updated.');
    }

    /* ═══════════════════════════════════════════════════════════════
       REPORTS FILTERS — ECR-backed multi-select source
       ───────────────────────────────────────────────────────────────
       OLD (matrix) report logic:
         1. Take all LMS users' emp_code values
         2. JOIN against ECR.Employee_Master ON EmployeeCode = emp_code
         3. JOIN with Company / Department / Division / Branch tables
         4. Return DISTINCT names — these are what filters should show

       Cached for 30 min (master data rarely changes).
       Falls back to empty array if ECR is unreachable.
       ═══════════════════════════════════════════════════════════════ */

    /** GET /api/Webservices/getCompanyList */
    public function getCompanyList(Request $request)
    {
        return $this->out(
            $this->getEcrFilterValues('company'),
            1, 'OK'
        );
    }

    /** GET /api/Webservices/getDepartmentList */
    public function getDepartmentList(Request $request)
    {
        return $this->out(
            $this->getEcrFilterValues('department'),
            1, 'OK'
        );
    }

    /** GET /api/Webservices/getVerticalList */
    public function getVerticalList(Request $request)
    {
        return $this->out(
            $this->getEcrFilterValues('vertical'),
            1, 'OK'
        );
    }

    /** GET /api/Webservices/getBranchList */
    public function getBranchList(Request $request)
    {
        return $this->out(
            $this->getEcrFilterValues('branch'),
            1, 'OK'
        );
    }

    /**
     * Single helper to get distinct ECR filter values.
     *
     * Returns: [ { name: "Calibehr Business Support Services Pvt. Ltd" }, ... ]
     * The `name` IS the value — both display and filter key are the same string,
     * because that's how the OLD matrix report stores it (no IDs are used).
     */
    private function getEcrFilterValues(string $type): array
    {
        $cacheKey = "lms.filter.$type";

        return Cache::remember($cacheKey, 1800, function () use ($type) {
            // 1. Collect all LMS user emp_codes
            $empCodes = DB::table('users')
                ->where('emp_status', 'A')
                ->whereNotNull('emp_code')
                ->where('emp_code', '!=', '')
                ->pluck('emp_code')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($empCodes) || ! function_exists('sqlsrv_connect')) {
                return [];
            }

            // 2. Build ECR query based on type
            $sqlMap = [
                'company' => 'SELECT DISTINCT C.CompanyName  AS name FROM [ECR_New].[dbo].[Employee_Master] EM
                                 LEFT JOIN [ECR_New].[dbo].[Company]    C ON EM.CompanyId    = C.ID
                                 WHERE C.CompanyName  IS NOT NULL AND EM.EmployeeCode IN (%s)
                                 ORDER BY C.CompanyName',
                'department' => 'SELECT DISTINCT DP.DeptName    AS name FROM [ECR_New].[dbo].[Employee_Master] EM
                                 LEFT JOIN [ECR_New].[dbo].[Department] DP ON EM.DepartmentId = DP.ID
                                 WHERE DP.DeptName    IS NOT NULL AND EM.EmployeeCode IN (%s)
                                 ORDER BY DP.DeptName',
                'vertical' => 'SELECT DISTINCT D.DivisionName AS name FROM [ECR_New].[dbo].[Employee_Master] EM
                                 LEFT JOIN [ECR_New].[dbo].[Division]   D  ON EM.DivisionId   = D.ID
                                 WHERE D.DivisionName IS NOT NULL AND EM.EmployeeCode IN (%s)
                                 ORDER BY D.DivisionName',
                'branch' => 'SELECT DISTINCT B.BranchName   AS name FROM [ECR_New].[dbo].[Employee_Master] EM
                                 LEFT JOIN [ECR_New].[dbo].[Branch]     B  ON EM.BranchId     = B.ID
                                 WHERE B.BranchName   IS NOT NULL AND EM.EmployeeCode IN (%s)
                                 ORDER BY B.BranchName',
            ];
            if (! isset($sqlMap[$type])) {
                return [];
            }

            // 3. Connect to ECR
            $serverName = env('ECR_SQLSRV_HOST', 'tcp:172.16.1.30,1433');
            $config = [
                'Database' => env('ECR_SQLSRV_DB', 'ECR_New'),
                'Uid' => env('ECR_SQLSRV_USER', 'nbg_sa'),
                'PWD' => env('ECR_SQLSRV_PASS', ''),
                'TrustServerCertificate' => true,
                'LoginTimeout' => 5,
            ];

            $results = [];
            try {
                $conn = @sqlsrv_connect($serverName, $config);
                if (! $conn) {
                    return [];
                }

                // Chunk emp_codes (SQL Server has a parameter limit)
                $chunks = array_chunk($empCodes, 1000);
                $names = [];

                foreach ($chunks as $chunk) {
                    // Quote each emp_code safely
                    $quoted = array_map(fn ($code) => "'".str_replace("'", "''", $code)."'", $chunk);
                    $inList = implode(',', $quoted);

                    $sql = sprintf($sqlMap[$type], $inList);
                    $stmt = sqlsrv_query($conn, $sql);
                    if (! $stmt) {
                        continue;
                    }

                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $name = trim($row['name'] ?? '');
                        if ($name !== '') {
                            $names[$name] = true;
                        }
                    }
                }
                sqlsrv_close($conn);

                $results = array_keys($names);
                sort($results, SORT_NATURAL | SORT_FLAG_CASE);

                // Output shape: [{ id, name }] — id is name itself for backward compat
                return array_map(fn ($n) => ['id' => $n, 'name' => $n], $results);
            } catch (\Throwable $e) {
                return [];
            }
        });
    }
}
