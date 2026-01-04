<?php

namespace app\model;

use think\Model;

class GeetestTable extends Model
{
    protected $name = 'GeetestTable';

    protected $table = 'GeetestTable';

    protected $pk = 'id';

    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id' => 'int',
        'token' => 'string',
        'group_id' => 'string',
        'user_id' => 'string',
        'code' => 'string',
        'verified' => 'int',
        'used' => 'int',
        'ip' => 'string',
        'user_agent' => 'string',
        'extra' => 'string',
        'expire_at' => 'int',
        'verified_at' => 'int',
        'used_at' => 'int',
        'created_at' => 'int',
        'updated_at' => 'int',
    ];

    protected $type = [
        'extra' => 'json',
    ];
}
