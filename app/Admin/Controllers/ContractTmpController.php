<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\UpdateCtmp;
use App\Admin\Repositories\ContractTmp;
use App\Models\SustainableAccount;
use App\Models\User;
use App\Services\ContractService;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;
use function foo\func;

class ContractTmpController extends AdminController
{
    protected $title = '临时下单';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
//        $user_id = request()->user_id;
//        $tmp = \App\Models\ContractTmp::where(['user_id' => $user_id, 'status' => 0])->first();
//
//        if ($user_id && !$tmp) {
//            Admin::script(
//                <<<JS
//    // 3秒后刷新当前页面
//    setTimeout(function () {
//        Dcat.reload();
//    }, 3000);
//    JS);
//        }
        return Grid::make(new ContractTmp(), function (Grid $grid) {
            $grid->model()->where('status', 0);
            $grid->column('id')->sortable();
            $grid->column('user_id', '用户ID');
            $grid->column('user_balance', '用户资金')->display(function () {
                return SustainableAccount::getContractAccount($this->user_id)->usable_balance;
            });
            $grid->column('symbol', '交易对');
            $grid->column('side', '方向')->using([1 => '开多', 2 => '开空'])->dot([1 => 'success', 2 => 'danger']);
            $grid->column('amount', '下单张数')->display(function () {
                $str = $this->amount;
                $user = User::find($this->user_id);
                $max = (new ContractService())->openNum($user, ['symbol' => $this->symbol, 'lever_rate' => $this->lever_rate]);
                if ($max == 0) {
                    $str .= '(无法下单)';
                } else {
                    $str .= '(约占' . strval(round($this->amount / $max, 2) * 100) . '%)';
                }
                return $str;
            });
            $grid->column('lever_rate', '杠杆');
            $grid->column('status', '状态')->using([0 => '委托中', 1 => '已完成', 2 => '失败']);
            $grid->column('delay_time', '最迟下单时间');
            $grid->column('fail_reason', '失败原因');
            $grid->column('mark', '标记')->using([1 => '自动', 2 => '干预']);
            $grid->column('created_at', '下单时间');
//            $grid->column('updated_at')->sortable();
            $grid->disableRowSelector();
            $grid->disableCreateButton();
            $grid->disableDeleteButton();
            $grid->disableEditButton();
            $grid->disableQuickEditButton();
            $grid->disableViewButton();
            $grid->actions([new UpdateCtmp()]);
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('user_id', '用户ID');
//                $filter->equal('status', '状态')->select([0 => '委托中', 1 => '已完成', 2 => '失败'])->default(0);
            });
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make($id, new ContractTmp(), function (Show $show) {
            $show->field('id');
            $show->field('user_id');
            $show->field('symbol');
            $show->field('side');
            $show->field('amount');
            $show->field('lever_rate');
            $show->field('status');
            $show->field('delay_time');
            $show->field('fail_reason');
            $show->field('mark');
            $show->field('created_at');
            $show->field('updated_at');
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new ContractTmp(), function (Form $form) {
            $form->display('id');
            $form->text('user_id');
            $form->text('symbol');
            $form->text('side');
            $form->text('amount');
            $form->text('lever_rate');
            $form->text('status');
            $form->text('delay_time');
            $form->text('fail_reason');
            $form->text('mark');

            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
