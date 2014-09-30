<?php

namespace ping\helpers;


class FileHelper
{
    const PATTERN_NODIR = 1;
    const PATTERN_ENDSWITH = 4;
    const PATTERN_MUSTBEDIR = 8;
    const PATTERN_NEGATIVE = 16;

    public static function normalizePath($path, $ds = DIRECTORY_SEPARATOR)
    {
        return rtrim(strtr($path, ['/' => $ds, '\\' => $ds]), $ds);
    }


    public static function copyDirectory($src, $dst, $options = [])
    {
        if(!is_dir($dst))
        {
            static::createDirectory($dst, isset($options['dirMode']) ? $options['dirMode'] : 0775, true);
        }

        $handle = opendir($src);
        if($handle === false)
        {
            throw new \Exception('Unable to open directory: ' . $src);
        }
        while(($file = readdir($handle)) !== false)
        {
            if($file === '.' || $file === '..')
            {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $file;
            $to = $dst . DIRECTORY_SEPARATOR . $file;
            if(static::filterPath($from, $options))
            {
                if(isset($options['beforeCopy']) && !call_user_func($options['beforeCopy'], $from, $to))
                {
                    continue;
                }
                if(is_file($from))
                {
                    copy($from, $to);
                    if(isset($options['fileMode']))
                    {
                        @chmod($to, $options['fileMode']);
                    }
                }
                else
                {
                    static::copyDirectory($from, $to, $options);
                }
                if(isset($options['afterCopy']))
                {
                    call_user_func($options['afterCopy'], $from, $to);
                }
            }
        }
        closedir($handle);
    }

    public static function removeDirectory($dir)
    {
        if(!is_dir($dir) || !($handle = opendir($dir)))
        {
            return;
        }
        while(($file = readdir($handle)) !== false)
        {
            if($file === '.' || $file === '..')
            {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if(is_file($path))
            {
                unlink($path);
            }
            else
            {
                static::removeDirectory($path);
            }
        }
        closedir($handle);
        rmdir($dir);
    }

    public static function findFiles($dir, $options = [])
    {
        if(!is_dir($dir))
        {
            throw new \Exception('The dir argument must be a directory.');
        }
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        if(!isset($options['basePath']))
        {
            $options['basePath'] = realpath($dir);
            // this should also be done only once
            if(isset($options['except']))
            {
                foreach($options['except'] as $key => $value)
                {
                    if(is_string($value))
                    {
                        $options['except'][$key] = self::parseExcludePattern($value);
                    }
                }
            }
            if(isset($options['only']))
            {
                foreach($options['only'] as $key => $value)
                {
                    if(is_string($value))
                    {
                        $options['only'][$key] = self::parseExcludePattern($value);
                    }
                }
            }
        }
        $list = [];
        $handle = opendir($dir);
        if($handle === false)
        {
            throw new \Exception('Unable to open directory: ' . $dir);
        }
        while(($file = readdir($handle)) !== false)
        {
            if($file === '.' || $file === '..')
            {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if(static::filterPath($path, $options))
            {
                if(is_file($path))
                {
                    $list[] = $path;
                }
                elseif(!isset($options['recursive']) || $options['recursive'])
                {
                    $list = array_merge($list, static::findFiles($path, $options));
                }
            }
        }
        closedir($handle);

        return $list;
    }

    public static function filterPath($path, $options)
    {
        if(isset($options['filter']))
        {
            $result = call_user_func($options['filter'], $path);
            if(is_bool($result))
            {
                return $result;
            }
        }

        if(empty($options['except']) && empty($options['only']))
        {
            return true;
        }

        $path = str_replace('\\', '/', $path);

        if(!empty($options['except']))
        {
            if(($except = self::lastExcludeMatchingFromList($options['basePath'], $path, $options['except'])) !== null)
            {
                return $except['flags'] & self::PATTERN_NEGATIVE;
            }
        }

        if(!is_dir($path) && !empty($options['only']))
        {
            if(($except = self::lastExcludeMatchingFromList($options['basePath'], $path, $options['only'])) !== null)
            {
                // don't check PATTERN_NEGATIVE since those entries are not prefixed with !
                return true;
            }

            return false;
        }

        return true;
    }

    public static function createDirectory($path, $mode = 0775, $recursive = true)
    {
        if(is_dir($path))
        {
            return true;
        }
        $parentDir = dirname($path);
        if($recursive && !is_dir($parentDir))
        {
            static::createDirectory($parentDir, $mode, true);
        }
        $result = mkdir($path, $mode);
        chmod($path, $mode);

        return $result;
    }

    private static function matchBasename($baseName, $pattern, $firstWildcard, $flags)
    {
        if($firstWildcard === false)
        {
            if($pattern === $baseName)
            {
                return true;
            }
        }
        elseif($flags & self::PATTERN_ENDSWITH)
        {
            /* "*literal" matching against "fooliteral" */
            $n = StringHelper::byteLength($pattern);
            if(StringHelper::byteSubstr($pattern, 1, $n) === StringHelper::byteSubstr($baseName, -$n, $n))
            {
                return true;
            }
        }

        return fnmatch($pattern, $baseName, 0);
    }

    private static function matchPathname($path, $basePath, $pattern, $firstWildcard, $flags)
    {
        // match with FNM_PATHNAME; the pattern has base implicitly in front of it.
        if(isset($pattern[0]) && $pattern[0] == '/')
        {
            $pattern = StringHelper::byteSubstr($pattern, 1, StringHelper::byteLength($pattern));
            if($firstWildcard !== false && $firstWildcard !== 0)
            {
                $firstWildcard--;
            }
        }

        $namelen = StringHelper::byteLength($path) - (empty($basePath) ? 0 : StringHelper::byteLength($basePath) + 1);
        $name = StringHelper::byteSubstr($path, -$namelen, $namelen);

        if($firstWildcard !== 0)
        {
            if($firstWildcard === false)
            {
                $firstWildcard = StringHelper::byteLength($pattern);
            }
            // if the non-wildcard part is longer than the remaining pathname, surely it cannot match.
            if($firstWildcard > $namelen)
            {
                return false;
            }

            if(strncmp($pattern, $name, $firstWildcard))
            {
                return false;
            }
            $pattern = StringHelper::byteSubstr($pattern, $firstWildcard, StringHelper::byteLength($pattern));
            $name = StringHelper::byteSubstr($name, $firstWildcard, $namelen);

            // If the whole pattern did not have a wildcard, then our prefix match is all we need; we do not need to call fnmatch at all.
            if(empty($pattern) && empty($name))
            {
                return true;
            }
        }

        return fnmatch($pattern, $name, FNM_PATHNAME);
    }

    private static function lastExcludeMatchingFromList($basePath, $path, $excludes)
    {
        foreach(array_reverse($excludes) as $exclude)
        {
            if(is_string($exclude))
            {
                $exclude = self::parseExcludePattern($exclude);
            }
            if(!isset($exclude['pattern']) || !isset($exclude['flags']) || !isset($exclude['firstWildcard']))
            {
                throw new \Exception('If exclude/include pattern is an array it must contain the pattern,
                flags and firstWildcard keys.');
            }
            if($exclude['flags'] & self::PATTERN_MUSTBEDIR && !is_dir($path))
            {
                continue;
            }

            if($exclude['flags'] & self::PATTERN_NODIR)
            {
                if(self::matchBasename(basename($path), $exclude['pattern'], $exclude['firstWildcard'], $exclude['flags']))
                {
                    return $exclude;
                }
                continue;
            }

            if(self::matchPathname($path, $basePath, $exclude['pattern'], $exclude['firstWildcard'], $exclude['flags']))
            {
                return $exclude;
            }
        }

        return null;
    }

    private static function parseExcludePattern($pattern)
    {
        if(!is_string($pattern))
        {
            throw new \Exception('Exclude/include pattern must be a string.');
        }
        $result = [
            'pattern'       => $pattern,
            'flags'         => 0,
            'firstWildcard' => false,
        ];
        if(!isset($pattern[0]))
        {
            return $result;
        }

        if($pattern[0] == '!')
        {
            $result['flags'] |= self::PATTERN_NEGATIVE;
            $pattern = StringHelper::byteSubstr($pattern, 1, StringHelper::byteLength($pattern));
        }
        $len = StringHelper::byteLength($pattern);
        if($len && StringHelper::byteSubstr($pattern, -1, 1) == '/')
        {
            $pattern = StringHelper::byteSubstr($pattern, 0, -1);
            $len--;
            $result['flags'] |= self::PATTERN_MUSTBEDIR;
        }
        if(strpos($pattern, '/') === false)
        {
            $result['flags'] |= self::PATTERN_NODIR;
        }
        $result['firstWildcard'] = self::firstWildcardInPattern($pattern);
        if($pattern[0] == '*' && self::firstWildcardInPattern(StringHelper::byteSubstr($pattern, 1, StringHelper::byteLength($pattern))) === false)
        {
            $result['flags'] |= self::PATTERN_ENDSWITH;
        }
        $result['pattern'] = $pattern;

        return $result;
    }

    private static function firstWildcardInPattern($pattern)
    {
        $wildcards = ['*', '?', '[', '\\'];
        $wildcardSearch = function ($r, $c) use ($pattern)
        {
            $p = strpos($pattern, $c);

            return $r === false ? $p : ($p === false ? $r : min($r, $p));
        };

        return array_reduce($wildcards, $wildcardSearch, false);
    }
}
