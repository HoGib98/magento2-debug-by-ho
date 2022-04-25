<?php

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2013 Jonathan Vollebregt (jnvsor@gmail.com), Rokas Šleinius (raveren@gmail.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Ho\Firephp\Kint;

use InvalidArgumentException;
use Ho\Firephp\Kint\Parser\Parser;
use Ho\Firephp\Kint\Parser\Plugin;
use Ho\Firephp\Kint\Renderer\Renderer;
use Ho\Firephp\Kint\Renderer\TextRenderer;
use Ho\Firephp\Kint\Zval\Value;
// use Ho\Firephp\Kint\Utils;

if (\defined('KINT_DIR')) {
    return;
}

if (\version_compare(PHP_VERSION, '5.6') < 0) {
    throw new Exception('Kint 4.0 requires PHP 5.6 or higher');
}

\define('KINT_DIR', __DIR__);
\define('KINT_WIN', DIRECTORY_SEPARATOR !== '/');
\define('KINT_PHP70', (\version_compare(PHP_VERSION, '7.0') >= 0));
\define('KINT_PHP71', (\version_compare(PHP_VERSION, '7.1') >= 0));
\define('KINT_PHP72', (\version_compare(PHP_VERSION, '7.2') >= 0));
\define('KINT_PHP73', (\version_compare(PHP_VERSION, '7.3') >= 0));
\define('KINT_PHP74', (\version_compare(PHP_VERSION, '7.4') >= 0));
\define('KINT_PHP80', (\version_compare(PHP_VERSION, '8.0') >= 0));

// Dynamic default settings
Kint::$file_link_format = \ini_get('xdebug.file_link_format');
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    Kint::$app_root_dirs = [
        $_SERVER['DOCUMENT_ROOT'] => '<ROOT>',
        \realpath($_SERVER['DOCUMENT_ROOT']) => '<ROOT>',
    ];
}

// Utils::composerSkipFlags();

if ((!\defined('KINT_SKIP_FACADE') || !KINT_SKIP_FACADE) && !\class_exists('Kint')) {
    \class_alias('Ho\\Firephp\\Kint\\Kint', 'Kint');
}

if (!\defined('KINT_SKIP_HELPERS') || !KINT_SKIP_HELPERS) {
    require_once __DIR__.'/init_helpers.php';
}

class Kint
{
    const MODE_RICH = 'r';
    const MODE_TEXT = 't';
    const MODE_CLI = 'c';
    const MODE_PLAIN = 'p';

    /**
     * @var mixed Kint mode
     *
     * false: Disabled
     * true: Enabled, default mode selection
     * other: Manual mode selection
     */
    public static $enabled_mode = true;

    /**
     * Default mode.
     *
     * @var string
     */
    public static $mode_default = self::MODE_RICH;

    /**
     * Default mode in CLI with cli_detection on.
     *
     * @var string
     */
    public static $mode_default_cli = self::MODE_CLI;

    /**
     * @var bool Return output instead of echoing
     */
    public static $return;

    /**
     * @var string format of the link to the source file in trace entries.
     *
     * Use %f for file path, %l for line number.
     *
     * [!] EXAMPLE (works with for phpStorm and RemoteCall Plugin):
     *
     * Kint::$file_link_format = 'http://localhost:8091/?message=%f:%l';
     */
    public static $file_link_format = '';

    /**
     * @var bool whether to display where kint was called from
     */
    public static $display_called_from = true;

    /**
     * @var array base directories of your application that will be displayed instead of the full path.
     *
     * Keys are paths, values are replacement strings
     *
     * [!] EXAMPLE (for Laravel 5):
     *
     * Kint::$app_root_dirs = [
     *     base_path() => '<BASE>',
     *     app_path() => '<APP>',
     *     config_path() => '<CONFIG>',
     *     database_path() => '<DATABASE>',
     *     public_path() => '<PUBLIC>',
     *     resource_path() => '<RESOURCE>',
     *     storage_path() => '<STORAGE>',
     * ];
     *
     * Defaults to [$_SERVER['DOCUMENT_ROOT'] => '<ROOT>']
     */
    public static $app_root_dirs = [];

    /**
     * @var int depth limit for array/object traversal. 0 for no limit
     */
    public static $depth_limit = 6;

    /**
     * @var bool expand all trees by default for rich view
     */
    public static $expanded = false;

    /**
     * @var bool enable detection when Kint is command line.
     *
     * Formats output with whitespace only; does not HTML-escape it
     */
    public static $cli_detection = true;

    /**
     * @var array Kint aliases. Add debug functions in Kint wrappers here to fix modifiers and backtraces
     */
    public static $aliases = [
        ['Ho\\Firephp\\Kint\\Kint', 'dump'],
        ['Ho\\Firephp\\Kint\\Kint', 'trace'],
        ['Ho\\Firephp\\Kint\\Kint', 'dumpArray'],
    ];

    /**
     * @var array<mixed, string> Array of modes to renderer class names
     */
    public static $renderers = [
        self::MODE_RICH => 'Ho\\Firephp\\Kint\\Renderer\\RichRenderer',
        self::MODE_PLAIN => 'Ho\\Firephp\\Kint\\Renderer\\PlainRenderer',
        self::MODE_TEXT => 'Ho\\Firephp\\Kint\\Renderer\\TextRenderer',
        self::MODE_CLI => 'Ho\\Firephp\\Kint\\Renderer\\CliRenderer',
    ];

    public static $plugins = [
        'Ho\\Firephp\\Kint\\Parser\\ArrayLimitPlugin',
        'Ho\\Firephp\\Kint\\Parser\\ArrayObjectPlugin',
        'Ho\\Firephp\\Kint\\Parser\\Base64Plugin',
        'Ho\\Firephp\\Kint\\Parser\\BlacklistPlugin',
        'Ho\\Firephp\\Kint\\Parser\\ClassMethodsPlugin',
        'Ho\\Firephp\\Kint\\Parser\\ClassStaticsPlugin',
        'Ho\\Firephp\\Kint\\Parser\\ClosurePlugin',
        'Ho\\Firephp\\Kint\\Parser\\ColorPlugin',
        'Ho\\Firephp\\Kint\\Parser\\DateTimePlugin',
        'Ho\\Firephp\\Kint\\Parser\\FsPathPlugin',
        'Ho\\Firephp\\Kint\\Parser\\IteratorPlugin',
        'Ho\\Firephp\\Kint\\Parser\\JsonPlugin',
        'Ho\\Firephp\\Kint\\Parser\\MicrotimePlugin',
        'Ho\\Firephp\\Kint\\Parser\\SimpleXMLElementPlugin',
        'Ho\\Firephp\\Kint\\Parser\\SplFileInfoPlugin',
        'Ho\\Firephp\\Kint\\Parser\\SplObjectStoragePlugin',
        'Ho\\Firephp\\Kint\\Parser\\StreamPlugin',
        'Ho\\Firephp\\Kint\\Parser\\TablePlugin',
        'Ho\\Firephp\\Kint\\Parser\\ThrowablePlugin',
        'Ho\\Firephp\\Kint\\Parser\\TimestampPlugin',
        'Ho\\Firephp\\Kint\\Parser\\TracePlugin',
        'Ho\\Firephp\\Kint\\Parser\\XmlPlugin',
    ];

    protected static $plugin_pool = [];

    protected $parser;
    protected $renderer;

    public function __construct(Parser $p, Renderer $r)
    {
        $this->parser = $p;
        $this->renderer = $r;
    }

    public function setParser(Parser $p)
    {
        $this->parser = $p;
    }

    public function getParser()
    {
        return $this->parser;
    }

    public function setRenderer(Renderer $r)
    {
        $this->renderer = $r;
    }

    public function getRenderer()
    {
        return $this->renderer;
    }

    public function setStatesFromStatics(array $statics)
    {
        $this->renderer->setStatics($statics);

        $this->parser->setDepthLimit(isset($statics['depth_limit']) ? $statics['depth_limit'] : 0);
        $this->parser->clearPlugins();

        if (!isset($statics['plugins'])) {
            return;
        }

        $plugins = [];

        foreach ($statics['plugins'] as $plugin) {
            if ($plugin instanceof Plugin) {
                $plugins[] = $plugin;
            } elseif (\is_string($plugin) && \is_subclass_of($plugin, 'Ho\\Firephp\\Kint\\Parser\\Plugin')) {
                if (!isset(static::$plugin_pool[$plugin])) {
                    /** @psalm-suppress UnsafeInstantiation */
                    $p = new $plugin();
                    static::$plugin_pool[$plugin] = $p;
                }
                $plugins[] = static::$plugin_pool[$plugin];
            }
        }

        $plugins = $this->renderer->filterParserPlugins($plugins);

        foreach ($plugins as $plugin) {
            $this->parser->addPlugin($plugin);
        }
    }

    public function setStatesFromCallInfo(array $info)
    {
        $this->renderer->setCallInfo($info);

        if (isset($info['modifiers']) && \is_array($info['modifiers']) && \in_array('+', $info['modifiers'], true)) {
            $this->parser->setDepthLimit(0);
        }

        $this->parser->setCallerClass(isset($info['caller']['class']) ? $info['caller']['class'] : null);
    }

    /**
     * Renders a list of vars including the pre and post renders.
     *
     * @param array $vars Data to dump
     * @param array $base Base Zval\Value objects
     *
     * @return string
     */
    public function dumpAll(array $vars, array $base)
    {
        if (\array_keys($vars) !== \array_keys($base)) {
            throw new InvalidArgumentException('Kint::dumpAll requires arrays of identical size and keys as arguments');
        }

        $output = $this->renderer->preRender();

        if ([] === $vars) {
            $output .= $this->renderer->renderNothing();
        }

        foreach ($vars as $key => $arg) {
            if (!$base[$key] instanceof Value) {
                throw new InvalidArgumentException('Kint::dumpAll requires all elements of the second argument to be Value instances');
            }
            $output .= $this->dumpVar($arg, $base[$key]);
        }

        $output .= $this->renderer->postRender();

        return $output;
    }

    /**
     * Dumps and renders a var.
     *
     * @param mixed $var  Data to dump
     * @param Value $base Base object
     *
     * @return string
     */
    public function dumpVar(&$var, Value $base)
    {
        return $this->renderer->render(
            $this->parser->parse($var, $base)
        );
    }

    /**
     * Gets all static settings at once.
     *
     * @return array Current static settings
     */
    public static function getStatics()
    {
        return [
            'aliases' => static::$aliases,
            'app_root_dirs' => static::$app_root_dirs,
            'cli_detection' => static::$cli_detection,
            'depth_limit' => static::$depth_limit,
            'display_called_from' => static::$display_called_from,
            'enabled_mode' => static::$enabled_mode,
            'expanded' => static::$expanded,
            'file_link_format' => static::$file_link_format,
            'mode_default' => static::$mode_default,
            'mode_default_cli' => static::$mode_default_cli,
            'plugins' => static::$plugins,
            'renderers' => static::$renderers,
            'return' => static::$return,
        ];
    }

    /**
     * Creates a Kint instances based on static settings.
     *
     * Also calls setStatesFromStatics for you
     *
     * @param array $statics array of statics as returned by getStatics
     *
     * @return null|\Kint\Kint
     */
    public static function createFromStatics(array $statics)
    {
        $mode = false;

        if (isset($statics['enabled_mode'])) {
            $mode = $statics['enabled_mode'];

            if (true === $mode && isset($statics['mode_default'])) {
                $mode = $statics['mode_default'];

                if (PHP_SAPI === 'cli' && !empty($statics['cli_detection']) && isset($statics['mode_default_cli'])) {
                    $mode = $statics['mode_default_cli'];
                }
            }
        }

        if (false === $mode) {
            return null;
        }

        if (!isset($statics['renderers'][$mode])) {
            $renderer = new TextRenderer();
        } else {
            /** @var Renderer */
            $renderer = new $statics['renderers'][$mode]();
        }

        /** @psalm-suppress UnsafeInstantiation */
        return new static(new Parser(), $renderer);
    }

    /**
     * Creates base objects given parameter info.
     *
     * @param array $params Parameters as returned from getCallInfo
     * @param int   $argc   Number of arguments the helper was called with
     *
     * @return Value[] Base objects for the arguments
     */
    public static function getBasesFromParamInfo(array $params, $argc)
    {
        static $blacklist = [
            'null',
            'true',
            'false',
            'array(...)',
            'array()',
            '[...]',
            '[]',
            '(...)',
            '()',
            '"..."',
            'b"..."',
            "'...'",
            "b'...'",
        ];

        $params = \array_values($params);
        $bases = [];

        for ($i = 0; $i < $argc; ++$i) {
            if (isset($params[$i])) {
                $param = $params[$i];
            } else {
                $param = null;
            }

            if (!isset($param['name']) || \is_numeric($param['name'])) {
                $name = null;
            } elseif (\in_array(\strtolower($param['name']), $blacklist, true)) {
                $name = null;
            } else {
                $name = $param['name'];
            }

            if (isset($param['path'])) {
                $access_path = $param['path'];

                if (!empty($param['expression'])) {
                    $access_path = '('.$access_path.')';
                }
            } else {
                $access_path = '$'.$i;
            }

            $bases[] = Value::blank($name, $access_path);
        }

        return $bases;
    }

    /**
     * Gets call info from the backtrace, alias, and argument count.
     *
     * Aliases must be normalized beforehand (Utils::normalizeAliases)
     *
     * @param array   $aliases Call aliases as found in Kint::$aliases
     * @param array[] $trace   Backtrace
     * @param int     $argc    Number of arguments
     *
     * @return array{params:null|array, modifiers:array, callee:null|array, caller:null|array, trace:array[]} Call info
     */
    public static function getCallInfo(array $aliases, array $trace, $argc)
    {
        $found = false;
        $callee = null;
        $caller = null;
        $miniTrace = [];

        foreach ($trace as $index => $frame) {
            if (Utils::traceFrameIsListed($frame, $aliases)) {
                $found = true;
                $miniTrace = [];
            }

            if (!Utils::traceFrameIsListed($frame, ['spl_autoload_call'])) {
                $miniTrace[] = $frame;
            }
        }

        if ($found) {
            $callee = \reset($miniTrace) ?: null;
            $caller = \next($miniTrace) ?: null;
        }

        foreach ($miniTrace as $index => $frame) {
            if ((0 === $index && $callee === $frame) || isset($frame['file'], $frame['line'])) {
                unset($frame['object'], $frame['args']);
                $miniTrace[$index] = $frame;
            } else {
                unset($miniTrace[$index]);
            }
        }

        $miniTrace = \array_values($miniTrace);

        $call = static::getSingleCall($callee ?: [], $argc);

        $ret = [
            'params' => null,
            'modifiers' => [],
            'callee' => $callee,
            'caller' => $caller,
            'trace' => $miniTrace,
        ];

        if ($call) {
            $ret['params'] = $call['parameters'];
            $ret['modifiers'] = $call['modifiers'];
        }

        return $ret;
    }

    /**
     * Dumps a backtrace.
     *
     * Functionally equivalent to Kint::dump(1) or Kint::dump(debug_backtrace(true))
     *
     * @return int|string
     */
    public static function trace()
    {
        if (false === static::$enabled_mode) {
            return 0;
        }

        Utils::normalizeAliases(static::$aliases);

        $call_info = static::getCallInfo(static::$aliases, \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), \func_num_args());

        $statics = static::getStatics();

        if (\in_array('~', $call_info['modifiers'], true)) {
            $statics['enabled_mode'] = static::MODE_TEXT;
        }

        $kintstance = static::createFromStatics($statics);
        if (!$kintstance) {
            // Should never happen
            return 0; // @codeCoverageIgnore
        }

        if (\in_array('-', $call_info['modifiers'], true)) {
            while (\ob_get_level()) {
                \ob_end_clean();
            }
        }

        $kintstance->setStatesFromStatics($statics);
        $kintstance->setStatesFromCallInfo($call_info);

        $trimmed_trace = [];
        $trace = \debug_backtrace(true);

        foreach ($trace as $frame) {
            if (Utils::traceFrameIsListed($frame, static::$aliases)) {
                $trimmed_trace = [];
            }

            $trimmed_trace[] = $frame;
        }

        \array_shift($trimmed_trace);

        $output = $kintstance->dumpAll(
            [$trimmed_trace],
            [Value::blank('Ho\\Firephp\\Kint\\Kint::trace()', 'debug_backtrace(true)')]
        );

        if (static::$return || \in_array('@', $call_info['modifiers'], true)) {
            return $output;
        }

        echo $output;

        if (\in_array('-', $call_info['modifiers'], true)) {
            \flush(); // @codeCoverageIgnore
        }

        return 0;
    }

    /**
     * Dumps some data.
     *
     * Functionally equivalent to Kint::dump(1) or Kint::dump(debug_backtrace(true))
     *
     * @return int|string
     */
    public static function dump($data)
    {
        $data=[$data];
        
        if (false === static::$enabled_mode) {
            return 0;
        }

        Utils::normalizeAliases(static::$aliases);

        $args = \func_get_args();

        $call_info = static::getCallInfo(static::$aliases, \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), \count($args));

        $statics = static::getStatics();

        if (\in_array('~', $call_info['modifiers'], true)) {
            $statics['enabled_mode'] = static::MODE_TEXT;
        }

        $kintstance = static::createFromStatics($statics);
        if (!$kintstance) {
            // Should never happen
            return 0; // @codeCoverageIgnore
        }

        if (\in_array('-', $call_info['modifiers'], true)) {
            while (\ob_get_level()) {
                \ob_end_clean();
            }
        }

        $kintstance->setStatesFromStatics($statics);
        $kintstance->setStatesFromCallInfo($call_info);

        $bases = static::getBasesFromParamInfo(
            isset($call_info['params']) ? $call_info['params'] : [],
            \count($args)
        );
        $output = $kintstance->dumpAll($args, $bases);

        if (static::$return || \in_array('@', $call_info['modifiers'], true)) {
            return $output;
        }

        echo $output;

        if (\in_array('-', $call_info['modifiers'], true)) {
            \flush(); // @codeCoverageIgnore
        }

        return 0;
    }

    /**
     * generic path display callback, can be configured in app_root_dirs; purpose is
     * to show relevant path info and hide as much of the path as possible.
     *
     * @param string $file
     *
     * @return string
     */
    public static function shortenPath($file)
    {
        $file = \array_values(\array_filter(\explode('/', \str_replace('\\', '/', $file)), 'strlen'));

        $longest_match = 0;
        $match = '/';

        foreach (static::$app_root_dirs as $path => $alias) {
            if (empty($path)) {
                continue;
            }

            $path = \array_values(\array_filter(\explode('/', \str_replace('\\', '/', $path)), 'strlen'));

            if (\array_slice($file, 0, \count($path)) === $path && \count($path) > $longest_match) {
                $longest_match = \count($path);
                $match = $alias;
            }
        }

        if ($longest_match) {
            $file = \array_merge([$match], \array_slice($file, $longest_match));

            return \implode('/', $file);
        }

        // fallback to find common path with Kint dir
        $kint = \array_values(\array_filter(\explode('/', \str_replace('\\', '/', KINT_DIR)), 'strlen'));

        foreach ($file as $i => $part) {
            if (!isset($kint[$i]) || $kint[$i] !== $part) {
                return ($i ? '.../' : '/').\implode('/', \array_slice($file, $i));
            }
        }

        return '/'.\implode('/', $file);
    }

    public static function getIdeLink($file, $line)
    {
        return \str_replace(['%f', '%l'], [$file, $line], static::$file_link_format);
    }

    /**
     * Returns specific function call info from a stack trace frame, or null if no match could be found.
     *
     * @param array $frame The stack trace frame in question
     * @param int   $argc  The amount of arguments received
     *
     * @return null|array{parameters:array, modifiers:array} params and modifiers, or null if a specific call could not be determined
     */
    protected static function getSingleCall(array $frame, $argc)
    {
        if (!isset($frame['file'], $frame['line'], $frame['function']) || !\is_readable($frame['file'])) {
            return null;
        }

        if (empty($frame['class'])) {
            $callfunc = $frame['function'];
        } else {
            $callfunc = [$frame['class'], $frame['function']];
        }

        $calls = CallFinder::getFunctionCalls(
            \file_get_contents($frame['file']),
            $frame['line'],
            $callfunc
        );

        $return = null;

        foreach ($calls as $call) {
            $is_unpack = false;

            // Handle argument unpacking as a last resort
            foreach ($call['parameters'] as $i => &$param) {
                if (0 === \strpos($param['name'], '...')) {
                    if ($i < $argc && $i === \count($call['parameters']) - 1) {
                        for ($j = 1; $j + $i < $argc; ++$j) {
                            $call['parameters'][] = [
                                'name' => 'array_values('.\substr($param['name'], 3).')['.$j.']',
                                'path' => 'array_values('.\substr($param['path'], 3).')['.$j.']',
                                'expression' => false,
                            ];
                        }

                        $param['name'] = 'reset('.\substr($param['name'], 3).')';
                        $param['path'] = 'reset('.\substr($param['path'], 3).')';
                        $param['expression'] = false;
                    } else {
                        $call['parameters'] = \array_slice($call['parameters'], 0, $i);
                    }

                    $is_unpack = true;
                    break;
                }

                if ($i >= $argc) {
                    continue 2;
                }
            }

            if ($is_unpack || \count($call['parameters']) === $argc) {
                if (null === $return) {
                    $return = $call;
                } else {
                    // If we have multiple calls on the same line with the same amount of arguments,
                    // we can't be sure which it is so just return null and let them figure it out
                    return null;
                }
            }
        }

        return $return;
    }

    public static function d($data){$data=[$data];
        if(is_null($data)){
            $str = "<i>NULL</i>";
        }elseif($data == ""){
            $str = "<i>Empty</i>";
        }elseif(is_array($data)){
            if(count($data) == 0){
                $str = "<i>Empty array.</i>";
            }else{
                $str = "<table style=\"border-bottom:0px solid #000;\" cellpadding=\"0\" cellspacing=\"0\">";
                foreach ($data as $key => $value) {
                    $str .= "<tr><td style=\"background-color:#008B8B; color:#FFF;border:1px solid #000;\">" . $key . "</td><td style=\"border:1px solid #000;\">" . d($value) . "</td></tr>";
                }
                $str .= "</table>";
            }
        }elseif(is_resource($data)){
            while($arr = mysql_fetch_array($data)){
                $data_array[] = $arr;
            }
            $str = self::d($data_array);
        }elseif(is_object($data)){
            $str = self::d(get_object_vars($data));
        }elseif(is_bool($data)){
            $str = "<i>" . ($data ? "True" : "False") . "</i>";
        }else{
            $str = $data;
            $str = preg_replace("/\n/", "<br>\n", $str);
        }
        return $str;
    }
    
    public static function dnl($data){$data=[$data];
        echo self::d($data) . "<br>\n";
    }
    
    public static function dd($data){$data=[$data];
        echo self::dnl($data);
    }
    
    public static function ddt($message = ""){
        echo "[" . date("Y/m/d H:i:s") . "]" . $message . "<br>\n";
    }
}
