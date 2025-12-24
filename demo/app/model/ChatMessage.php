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
class ChatMessage extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chat_messages';

    protected $fillable = ['id', 'chat_session_id', 'model', 'role', 'content', 'extends'];
    protected $casts = ['extends' => 'json'];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id', 'id');
    }
}