<?php

namespace App\Services;

/**
 * EcrService — SQL Server connection helper for Calibehr ECR (HRMS) database.
 *
 * Centralizes all ECR queries so controllers never call env() or sqlsrv_*
 * directly. If ECR is unreachable, all methods return null gracefully.
 *
 * Usage:
 *   $ecr = new EcrService();
 *   $name = $ecr->getDesignationName($designationId);  // "Senior Developer" or null
 *   $ecr->close();
 */
class EcrService
{
    private mixed $conn = null;

    public function __construct()
    {
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $serverName = config('services.ecr.host');
            $config = [
                'Database'               => config('services.ecr.database'),
                'Uid'                    => config('services.ecr.username'),
                'PWD'                    => config('services.ecr.password'),
                'TrustServerCertificate' => true,
                'LoginTimeout'           => config('services.ecr.timeout', 5),
            ];
            $this->conn = @sqlsrv_connect($serverName, $config);
        } catch (\Throwable) {
            $this->conn = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->conn !== null;
    }

    public function getDesignationName(mixed $id): ?string
    {
        if (!$this->conn || empty($id) || !is_numeric($id)) return null;
        return $this->fetchSingle(
            "SELECT TOP 1 DesignationName FROM [ECR_New].[dbo].[Designation] WHERE ID = ?",
            [(int)$id],
            'DesignationName'
        );
    }

    public function getDepartmentName(mixed $id): ?string
    {
        if (!$this->conn || empty($id) || !is_numeric($id) || (int)$id <= 0) return null;
        return $this->fetchSingle(
            "SELECT TOP 1 DeptName FROM [ECR_New].[dbo].[Department] WHERE ID = ?",
            [(int)$id],
            'DeptName'
        );
    }

    public function getBranchName(mixed $id): ?string
    {
        if (!$this->conn || empty($id) || !is_numeric($id) || (int)$id <= 0) return null;
        return $this->fetchSingle(
            "SELECT TOP 1 BranchName FROM [ECR_New].[dbo].[Branch] WHERE ID = ?",
            [(int)$id],
            'BranchName'
        );
    }

    public function getEmployeeDepartmentId(string $empCode): ?int
    {
        if (!$this->conn || empty($empCode)) return null;
        $val = $this->fetchSingle(
            "SELECT TOP 1 DepartmentId FROM [ECR_New].[dbo].[Employee] WHERE EmpCode = ?",
            [$empCode],
            'DepartmentId'
        );
        return $val !== null ? (int)$val : null;
    }

    /**
     * Returns all departments as [id => name] map.
     * Used by leaderboard department grouping.
     */
    public function getAllDepartments(): array
    {
        if (!$this->conn) return [];
        try {
            $stmt = sqlsrv_query($this->conn, 'SELECT ID, DeptName FROM [ECR_New].[dbo].[Department]');
            if (!$stmt) return [];
            $map = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $map[(string)$row['ID']] = $row['DeptName'];
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchSingle(string $sql, array $params, string $column): ?string
    {
        try {
            $stmt = sqlsrv_query($this->conn, $sql, $params);
            if (!$stmt) return null;
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            return ($row && isset($row[$column])) ? (string)$row[$column] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function close(): void
    {
        if ($this->conn) {
            @sqlsrv_close($this->conn);
            $this->conn = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
