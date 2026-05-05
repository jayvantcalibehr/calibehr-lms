<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PushNotificationToken extends Model {
    protected $table = 'push_notification_tokens';
    public $timestamps = false;
    protected $fillable = ['id','emp_id','device_type','token','added_on','updated_on'];
}
