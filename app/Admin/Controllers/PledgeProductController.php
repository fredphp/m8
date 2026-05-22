<?php

namespace App\Admin\Controllers;

use App\Models\Coins;
use App\Models\PledgeProduct;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;

class PledgeProductController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new PledgeProduct(), function (Grid $grid) {
            $grid->disableBatchDelete();
//            $grid->actions(function (Grid\Displayers\Actions $actions) {
//                $actions->disableDelete();
//            });
            $grid->model()->orderByDesc("id");

            $grid->id->sortable();
            $grid->name;
            $grid->cover->image('',50,50);
            $grid->spread_img->image('',50,50);
            $grid->coin_name;
            $grid->cycle;
            $grid->rate;
            $grid->min_amount;
            $grid->max_amount;

            $grid->column('two_coin_name','发行币');
            $grid->column('proportion','占比');
            $grid->can_buy_num;
            
            $grid->is_invit_unlock->switch();
            $grid->column('is_invit_unlock','邀请解锁')->switch();
            $grid->invit_num;
            $grid->column('invit_num','邀请人数');
            //$grid->column('status','状态')->using(PledgeProduct::$statusMap)->dot([0=>'danger',1=>'success']);
            $grid->status->switch();
            //$grid->switch('status', '是否上架');
            $grid->created_at->sortable();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('name')->width(2);
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
        return Show::make($id, new PledgeProduct(), function (Show $show) {
            $show->id;
            $show->coin_name;
            $show->cover;
            $show->spread_img;
            $show->name;
            $show->cycle;
            $show->rate;
            $show->min_amount;
            $show->max_amount;
            $show->two_coin_name;
            $show->proportion;
            $show->can_buy_num;
            $show->is_invit_unlock;
            $show->invit_num;
            $show->content;
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
        return Form::make(new PledgeProduct(), function (Form $form) {
            $form->disableDeleteButton();

            $options = Coins::query()->where('status', 1)->orderByDesc('coin_id')->pluck('coin_name',
                'coin_id')->toArray();

            $form->display('id');
            $form->select('coin_id')->options($options)->rules("required");;
            $form->hidden('coin_name');
            $form->image('cover')->uniqueName()->autoUpload();
            $form->image('spread_img')->uniqueName()->autoUpload();
            $form->text('name')->rules("required");
            $form->number('cycle')->rules("required|gte:1");
            $form->number('rate')->rules("required|gte:1");
            $form->decimal('min_amount')->rules("required|gt:0");
            $form->decimal('max_amount')->rules("required|max:9");
            $form->number('proportion','占比');
            $form->number('can_buy_num')->rules("required|gte:1");
            $form->switch('is_invit_unlock','邀请解锁')->default(1);
            $form->number('invit_num','邀请人数')->rules("required");
            $form->switch('status')->default(1);
            $form->textarea('content');
            $form->display('created_at');
            $form->display('updated_at');

            $form->hidden('two_coin_id');
            $form->hidden('two_coin_name');


            $form->saving(function (Form $form) use ($options) {
                if ($form->isCreating() || $form->isEditing()) {
                    if (!blank($form->coin_id)) {
                        $coin_id         = $form->coin_id;
                        $coin_name       = $options[$coin_id];
                        $form->coin_id   = $coin_id;
                        $form->coin_name = $coin_name;
                    }
                    $two_coin_id = 0;
                    $two_coin_name = '';

                    if($form->proportion != 0){
                        $two_coin_id = 26;
                        $two_coin_name = 'MED';
                    }
                    $form->two_coin_id = $two_coin_id;
                    $form->two_coin_name = $two_coin_name;
                }
            });
        });
    }
}
