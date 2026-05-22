<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\User\CheckAuth;
use App\Admin\Actions\User\PassUserAuth;
use App\Admin\Actions\User\RejectUserAuth;
use App\Admin\Actions\User\ResetUserAuth;
use App\Models\Agent;
use App\Models\AgentGrade;
use App\Models\User;
use App\Models\UserAuth;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;

class UserAuthController extends AdminController
{
    protected $title= '实名认证';
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(UserAuth::with(['user']), function (Grid $grid) {
            $grid->model()->orderByRaw("FIELD(status," . implode(",", [1, 0, 3, 2]) . ")")->orderByDesc('created_at');
            if (Admin::user()->inRoles(['员工', '经理级'])) {
                $preUser = User::where('username', Admin::user()->username)->first();
                $lowerIds = getLowerIds($preUser->user_id);
                $grid->model()->whereIn('user_id',$lowerIds);
            };
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->disableDelete();
                $actions->disableQuickEdit();
                $actions->disableEdit();
                $actions->disableView();
                if (Admin::user()->inRoles(['超级管理员', '经理级'])) {
//                    if($actions->row->status == UserAuth::STATUS_WAIT){
                        $actions->append(new CheckAuth());
//                    }
                }
            });

            $grid->disableCreateButton();
            $grid->disableDeleteButton();
            $grid->disableEditButton();
            if (Admin::user()->inRoles(['超级管理员', '经理级'])) {
                $grid->tools([
                    new PassUserAuth(),
                    new RejectUserAuth(),
                    new ResetUserAuth(),
                ]);
            }
            if (!Admin::user()->inRoles(['超级管理员', '经理级'])) {
                $grid->disableRowSelector();
            }
            $grid->toolsWithOutline(false);

            $grid->id->sortable();

            $grid->user_id;
            $grid->realname;
            $grid->country_code;
            $grid->id_card;
            $grid->front_img->image('',50,50);
            $grid->back_img->image('',50,50);
            $grid->hand_img->image('',50,50);
            $grid->check_time;
            $grid->primary_status->using(UserAuth::$primaryStatusMap)->dot([0=>'default',1=>'success']);
            $grid->status->using(UserAuth::$statusMap)->dot([0=>'default',1=>'danger',2=>'success',3=>'primary'])->filter(
                Grid\Column\Filter\In::make(UserAuth::$statusMap)
            );
            $grid->created_at->sortable();
//            $grid->updated_at->sortable();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('user_id', '会员ID')->width(3);
                $filter->where('username',function($q){
                    $username = $this->input;
                    $q->whereHas('user',function($q)use($username){
                        $q->where('username',$username)->orWhere('phone',$username)->orWhere('email',$username);
                    });
                },"用户名/手机/邮箱")->width(3);
                 $filter->equal('status', '高级认证')->select(UserAuth::$statusMap)->width(3);

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
        return Show::make($id, new UserAuth(), function (Show $show) {
            $show->id;
            $show->user_id;
            $show->realname;
            $show->country_code;
            $show->id_card;
            $show->front_img;
            $show->back_img;
            $show->hand_img;
            $show->check_time;
            $show->status;
            $show->created_at;
            $show->updated_at;
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new UserAuth(), function (Form $form) {
            $form->display('id');
            $form->text('user_id');
            $form->text('realname');
            $form->text('country_code');
            $form->text('id_card');
            $form->text('front_img');
            $form->text('back_img');
            $form->text('hand_img');
            $form->text('check_time');
            $form->text('status');

            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
