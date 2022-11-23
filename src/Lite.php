<?php
namespace PhalApi\CLI;

/**
 * 用于开发命令行应用的扩展类库
 *
 * - Example
```
    $cli = new PhalApi\CLI\Lite();
    $cli->response();
```
 *
 * - Usage
 ```
    ./cli -s Site.Index --username dogstar
``` 
 *
 * @link http://getopt-php.github.io/getOopt-php/example.html
 * @author dogstar <chanzonghuang@gmail.com> 20170205
 */

//require_once dirname(__FILE__) . '/Ulrichsg/Getopt/Getopt.php';
//require_once dirname(__FILE__) . '/Ulrichsg/Getopt/Option.php';
//require_once dirname(__FILE__) . '/Ulrichsg/Getopt/Argument.php';
//require_once dirname(__FILE__) . '/Ulrichsg/Getopt/CommandLineParser.php';
//require_once dirname(__FILE__) . '/Ulrichsg/Getopt/OptionParser.php';
//
//use Ulrichsg\Getopt\Getopt;
//use Ulrichsg\Getopt\Option;
//use Ulrichsg\Getopt\Argument;

use GetOpt\GetOpt;
use GetOpt\Option;
use GetOpt\Command;
use GetOpt\Argument;;
use GetOpt\ArgumentException;
use GetOpt\ArgumentException\Missing;

use PhalApi\PhalApi;
use PhalApi\Request;
use PhalApi\ApiFactory;
use PhalApi\Exception;

class Lite {

    public function response() {
        // 解析获取service参数
        $serviceOpt = Option::create('s', 'service', Getopt::REQUIRED_ARGUMENT)
            ->setDescription('接口服务');
        $helpOpt = Option::create('h', 'help')
            ->setDescription('查看帮助信息');

        $getOpt = new Getopt(array(
            $serviceOpt,
            $helpOpt
        ));

        $service = NULL;
        try {
            try {
                $getOpt->process();

                $service = $getOpt['service'];
                if ($service === NULL) {
                    echo $getOpt->getHelpText();
                    echo PHP_EOL . "Error: 缺少service参数" . PHP_EOL;
                    exit(1);
                }
            } catch (Missing $ex) {
                // catch missing exceptions if help is requested
                if (!$getOpt->getOption('help')) {
                    throw $ex;
                }
            }
        } catch (ArgumentException $ex) {
            echo $getOpt->getHelpText();
            echo PHP_EOL . $ex->getMessage() . PHP_EOL;
            exit(1);
        }

        // 再转换处理 。。。
        try{
            // 获取接口实例
            $rules = array();
            try {
                \PhalApi\DI()->request = new Request(array('service' => $service));
                $api = ApiFactory::generateService(false);
                $rules = $api->getApiRules();
            } catch (Exception $ex){
                throw new \UnexpectedValueException($ex->getMessage());
            }

            // PhalApi接口参数转换为命令行参数
            $rule2opts = array();
            foreach ($rules as $ruleKey => $ruleItem) {
                $opt = Option::create(null, $ruleItem['name'], !empty($ruleItem['require']) ? Getopt::REQUIRED_ARGUMENT : Getopt::OPTIONAL_ARGUMENT);

                if (isset($ruleItem['default'])) {
                    $opt->setArgument(new Argument($ruleItem['default']));
                }

                if (isset($ruleItem['desc'])) {
                    $opt->setDescription($ruleItem['desc']);
                }

                $rule2opts[] = $opt;
            }

            // 优化：http://qa.phalapi.net/?/question/1499
            if (empty($rule2opts)) {
                $rule2opts[] = $helpOpt;
            }

            // 添加参数选项，提取命令行参数并重新注册请求
            $getOpt->addOptions($rule2opts);

            try {
                $getOpt->process();
            } catch (Missing $ex) {
                // catch missing exceptions if help is requested
                if (!$getOpt->getOption('help')) {
                    throw $ex;
                }
            }

            if ($getOpt['help']) {
                echo $getOpt->getHelpText();
                exit(1);
            }

            \PhalApi\DI()->request = new Request($getOpt->getOptions());

            // 转交PhalApi重新响应处理
            $api = new PhalApi();
            $rs = $api->response();
            $rs->output();
        } catch (ArgumentException $ex) {
            echo $getOpt->getHelpText();
            echo PHP_EOL . $ex->getMessage() . PHP_EOL;
            exit(1);
        }
    }
}
