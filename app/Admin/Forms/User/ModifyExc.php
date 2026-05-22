<?php

namespace App\Admin\Forms\User;

use App\Models\Coins;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\VolLog;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ModifyExc extends Form
{
    // 增加一个自定义属性保存ID
    protected $user_id;

    // 构造方法的参数必须设置默认值
    public function __construct($user_id = null)
    {
        $this->user_id = $user_id;

        parent::__construct();
    }

    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return Response
     */
    public function handle(array $input)
    {

        $user_id = $input['user_id'] ?? null;
        if (! $user_id) {
            return $this->error('参数错误');
        }
        $user = User::query()->find($user_id);
        if (! $user) return $this->error('记录不存在');
        $vlog = new VolLog();
        $vlog->user_id = $user_id;
        $vlog->op = $input['op'];
        $vlog->number = $input['op'].$input['number'];
        $vlog->remark = $input['remark'];
        $vlog->op_time = date('Y-m-d H:i:s');
        if (!$vlog->save()) {
            return $this->error('操作失败');
        }
        return $this->success('操作成功');
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->select('op','操作符')->options([
            '+' => '增加',
            '-' => '减少'
        ])->required()->default('+');
        $this->text('number','数量')->required()->default('1');
        $this->textarea('remark','原因');
        // 设置隐藏表单，传递用户id
        $this->hidden('user_id')->value($this->user_id);
    }

    /**
     * The data of the form.
     *
     * @return array
     */
    public function default()
    {
        return [];
    }
}
