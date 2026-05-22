<?php

namespace App\Admin\Actions\TimeOrder;

use App\Models\TimeOrder;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\BatchAction;
use Dcat\Admin\Traits\HasPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SetLoss extends BatchAction
{
    protected $style = 'btn btn-sm btn-default';

    /**
     * @return string
     */
	protected $title = '亏损设置';


    /**
     * Handle the action request.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function handle(Request $request)
    {
        $keys = $this->getKey();
        TimeOrder::query()->whereIn('id', $keys)->where(['follow_id' => 0])->where('status','!=',2)->update(['kongyk' => 2]);
        return $this->response()->success('Processed successfully.')->refresh();
    }

    /**
     * @return string|array|void
     */
    public function confirm()
    {
        // return ['Confirm?', 'contents'];
        return ['确定要设置吗？(只能设置待下单的订单)'];
    }


    /**
     * @param Model|Authenticatable|HasPermissions|null $user
     *
     * @return bool
     */
    protected function authorize($user): bool
    {
        return true;
    }

    /**
     * @return array
     */
    protected function parameters()
    {
        return [];
    }

    public function actionScript(){
        $warning = "请选择要设置的行数！";

        return <<<JS
function (data, target, action) {
    console.log('发起请求之前', {data, target, action});
    var key = {$this->getSelectedKeysScript()}

    if (key.length === 0) {
        Dcat.warning('{$warning}');
        return false;
    }

    // 设置主键为复选框选中的行ID数组
    action.options.key = key;
}
JS;
    }
    protected function html()
    {

            return <<<HTML
<a {$this->formatHtmlAttributes()}><button class="btn btn-danger btn-mini" style="background: red;color: white;">{$this->title()}</button></a>
HTML;

    }
}
