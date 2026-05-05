<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model {
    protected $table = 'app_versions';
    public $timestamps = false;
    protected $fillable = ['id','device','app_version','updated_date'];
}
