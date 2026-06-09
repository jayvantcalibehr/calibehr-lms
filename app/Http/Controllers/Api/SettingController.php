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
            $arr['roles'] = $rolesByUser[(string) $u->id] ?? [];

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

    public function getUserDetail(Request $request)
    {
        $request->validate(['userID' => 'required|integer']);
        $user = User::findOrFail($request->userID);
        $roles = UserRole::where('user_id', $user->id)->pluck('role_id');
        return $this->out(array_merge($user->toArray(), ['roles' => $roles]), 1, 'OK');
    }

    public function getAllRoles(Request $request)
    {
        $roles = RoleMaster::where('status', 1)->get();
        return $this->out($roles, 1, 'OK');
    }

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

    /** GET /api/Webservices/getCompanyList */
    public function getCompanyList(Request $request)
    {
        return $this->out($this->getEcrFilterValues('company'), 1, 'OK');
    }

    /** GET /api/Webservices/getDepartmentList */
    public function getDepartmentList(Request $request)
    {
        return $this->out($this->getEcrFilterValues('department'), 1, 'OK');
    }

    /** GET /api/Webservices/getVerticalList */
    public function getVerticalList(Request $request)
    {
        return $this->out($this->getEcrFilterValues('vertical'), 1, 'OK');
    }

    /** GET /api/Webservices/getBranchList */
    public function getBranchList(Request $request)
    {
        return $this->out($this->getEcrFilterValues('branch'), 1, 'OK');
    }

    /**
     * FIX: Removed emp_codes IN clause — was causing timeout with 1.29 lakh codes.
     * Now fetches directly from ECR master tables — much faster.
     * Cached for 30 min.
     */
    private function getEcrFilterValues(string $type): array
    {
        $cacheKey = "lms.filter.$type";

        return Cache::remember($cacheKey, 1800, function () use ($type) {

            if (! function_exists('sqlsrv_connect')) {
                return [];
            }

            $sqlMap = [
                'company' => 'SELECT DISTINCT C.CompanyName AS name
                              FROM [ECR_New].[dbo].[Company] C
                              WHERE C.CompanyName IS NOT NULL AND C.CompanyName != \'\'
                              ORDER BY C.CompanyName',

                'department' => 'SELECT DISTINCT DP.DeptName AS name
                                 FROM [ECR_New].[dbo].[Department] DP
                                 WHERE DP.DeptName IS NOT NULL AND DP.DeptName != \'\'
                                 ORDER BY DP.DeptName',

                'vertical' => 'SELECT DISTINCT D.DivisionName AS name
                               FROM [ECR_New].[dbo].[Division] D
                               WHERE D.DivisionName IS NOT NULL AND D.DivisionName != \'\'
                               ORDER BY D.DivisionName',

                'branch' => 'SELECT DISTINCT B.BranchName AS name
                             FROM [ECR_New].[dbo].[Branch] B
                             WHERE B.BranchName IS NOT NULL AND B.BranchName != \'\'
                             ORDER BY B.BranchName',
            ];

            if (! isset($sqlMap[$type])) {
                return [];
            }

            try {
                $serverName = config('services.ecr.host');
                $config = [
                    'Database'               => config('services.ecr.database'),
                    'Uid'                    => config('services.ecr.username'),
                    'PWD'                    => config('services.ecr.password'),
                    'TrustServerCertificate' => true,
                    'LoginTimeout'           => 5,
                ];

                $conn = @sqlsrv_connect($serverName, $config);
                if (! $conn) {
                    return [];
                }

                $stmt = sqlsrv_query($conn, $sqlMap[$type]);
                if (! $stmt) {
                    sqlsrv_close($conn);
                    return [];
                }

                $names = [];
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $name = trim($row['name'] ?? '');
                    if ($name !== '') {
                        $names[$name] = true;
                    }
                }
                sqlsrv_close($conn);

                $results = array_keys($names);
                sort($results, SORT_NATURAL | SORT_FLAG_CASE);

                return array_map(fn ($n) => ['id' => $n, 'name' => $n], $results);

            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    public function toggleUserStatus(Request $request)
    {
        $request->validate(['userID' => 'required|integer']);
        $user = User::findOrFail($request->userID);
        $currentId = auth('sanctum')->id();
        if ($user->id === $currentId) {
            return $this->out(null, 0, 'You cannot disable your own account.');
        }
        $isActive = $user->emp_active === 'A' && $user->emp_status === 'A';
        if ($isActive) {
            $user->emp_active = 'I'; $user->emp_status = 'I'; $user->save();
            return $this->out(['status' => 'inactive'], 1, 'User disabled successfully.');
        } else {
            $user->emp_active = 'A'; $user->emp_status = 'A'; $user->save();
            return $this->out(['status' => 'active'], 1, 'User enabled successfully.');
        }
    }
}
