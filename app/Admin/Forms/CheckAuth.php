<?php

namespace App\Admin\Forms;

use App\Models\User;
use App\Models\UserAuth;
use App\Notifications\CommonNotice;
use Carbon\Carbon;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckAuth extends Form
{
    // 增加一个自定义属性保存ID
    protected $id;

    // 构造方法的参数必须设置默认值
    public function __construct($id = null)
    {
        $this->id = $id;

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
        $id = $input['id'] ?? null;
        $status = $input['status'] ?? 1;
        $remark = $input['remark'] ?? '';

        if (! $id) {
            return $this->error('参数错误');
        }

        $item = UserAuth::find($id);
        if (! $item) {
            return $this->error('记录不存在');
        }
        $user = User::query()->find($item['user_id']);

        DB::beginTransaction();
        try{
            // 更新记录
            $item->update(['status' => $status == 1 ? UserAuth::STATUS_AUTH : UserAuth::STATUS_REJECT, 'check_time' => Carbon::now()->toDateTimeString() ,'remark' => $remark]);

            if($status == 1){
                $user->update(['user_auth_level'=>User::user_auth_level_top]);
                $user->notify(new CommonNotice(['title' => 'KYC认证审核成功','content' => "KYC认证审核成功"]));
            }
            if($status == 2){
                $user->notify(new CommonNotice(['title' => 'KYC认证审核已驳回','content' => "KYC认证审核已驳回"]));
            }

            // status ==3 重置用户认证状态
            if ($status == 3){
                $item->update(['status' => 0,'primary_status' =>0]);
                $user = User::query()->find($item['user_id']);
                $user->update(['user_auth_level'=>User::user_auth_level_wait]);
            }

            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            throw $e;
        }

        return $this->success('审核成功');
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->radio('status')->options([1=>'审核通过',2=>'审核拒绝',3=>'重置认证'])->rules('required|in:1,2,3');
        $this->textarea('remark','备注');

        // 设置隐藏表单，传递用户id
        $this->hidden('id')->value($this->id);
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
