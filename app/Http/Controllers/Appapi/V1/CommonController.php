<?php

namespace App\Http\Controllers\Appapi\V1;

use App\Models\AppVersion;
use App\Models\Country;
use App\Models\InsideTradePair;
use App\Models\Translate;
use App\Models\User;
use App\Services\HuobiService\HuobiapiService;
use Aws\Common\Credentials\Credentials;
use Aws\S3\S3Client;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Cached\Storage\Adapter;
use League\Flysystem\Filesystem;

class CommonController extends ApiController
{
    //

    public function getNewestVersion()
    {
        $androidV = AppVersion::getNewestVersion(AppVersion::client_type_android);
        $iosV = AppVersion::getNewestVersion(AppVersion::client_type_ios);

        return $this->successWithData(['android' => $androidV,'ios' => $iosV]);
    }

    public function getTranslate(Request $request)
    {
        if ($vr = $this->verifyField($request->all(),[
            'lang' => '',
        ])) return $vr;

        $lang = $request->input('lang','zh-CN');

        $data = Translate::getTranslate($lang);
        return $this->successWithData($data);
    }

    public function uploadImage(Request $request)
    {
//        if ($vr = $this->verifyField($request->all(),[
//            'image' => 'required|image',
//        ])) return $vr;
//
//        $disk_type = 's3';
//        $disk = \Illuminate\Support\Facades\Storage::disk($disk_type);
//        $re = $disk->put('upload',$request->image);
//        $data = ['url' => getFullPath($re)  ,'path' => $re];
//        return $this->successWithData($data,'上传成功');
        $file = $request->file('image');
        $s3 = new S3Client([
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => 'latest',
        ]);
        $bucket = env('AWS_BUCKET');
        // 获取文件相关信息
        $type = $file->getClientMimeType();     // image/jpeg
        $fileName = md5(time().mt_rand(100000,999999)) . '.' . $file->getClientOriginalExtension();
        try {
            $result = $s3->putObject([
                'Bucket'      => $bucket,
                'Key'         => $fileName,
                'Body' => fopen($file->getRealPath(), 'r'),
                'ACL'         => 'public-read',
                'ContentType' => $type,
            ]);
            $url = $s3->getObjectUrl(env('AWS_BUCKET'), $fileName);
            $data['url'] = $url;
            $data['path'] = '';
            return $this->successWithData($data,'上传成功');
        } catch (Exception $e) {
            return $this->error(4001,'上传成功');
        }
    }

    public function getCountryList()
    {
        $data = Country::getForeverCachedCountry();
        return $this->successWithData($data);
    }

}
