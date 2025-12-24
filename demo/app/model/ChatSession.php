<?php
/**
 * ChatSession.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use support\Model;

/**
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ChatSession extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chat_sessions';

    protected $fillable = ['id', 'sid', 'session_id', 'session_title'];

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_id', 'id');
    }

    public static function getSession(string $sessionId, string $sid)
    {
        return self::where('session_id', $sessionId)->where('sid', $sid)->first();
    }
}