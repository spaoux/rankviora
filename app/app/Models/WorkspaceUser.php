<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkspaceUser extends Pivot
{
    protected $table = 'workspace_user';

    public $incrementing = true;

    protected $fillable = ['workspace_id', 'user_id', 'role'];
}
