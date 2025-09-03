<?php
// Bootstrap stubs for PHPStan (runtime-aliased minimal Joomla classes)
// Purpose: satisfy symbol discovery without analyzing signatures.

namespace Joomla\Registry {
    class Registry
    {
        public function get($key, $default = null)
        {
            return $default;
        }
    }
}

namespace Joomla\CMS\Plugin {
    class CMSPlugin
    {
        public $params;
        public function __construct() {}
    }
}

namespace Joomla\CMS\Application {
    class CMSApplication
    {
        public function isClient($c)
        {
            return true;
        }
        public function get($key, $default = null)
        {
            return $default;
        }
        public function getMenu($c = null)
        {
            return new class {
                public function getMenu()
                {
                    return [];
                }
                public function getActive()
                {
                    return (object)['id' => 1, 'home' => false];
                }
                public function getDefault()
                {
                    return (object)['language' => '*', 'home' => 1];
                }
            };
        }
        public function setHeader($a, $b, $c = null) {}
        public function setBody($b) {}
        public function getBody()
        {
            return '';
        }
        public function respond() {}
        public function close() {}
        public function getInput()
        {
            return new class {
                public function getCmd($k)
                {
                    return '';
                }
                public function getInt($k)
                {
                    return 0;
                }
                public function get($k)
                {
                    return null;
                }
            };
        }
        public function getPathway()
        {
            return new class {
                public function getPathway()
                {
                    return [];
                }
            };
        }
        public function getDocument()
        {
            return new \Joomla\CMS\Document\HtmlDocument();
        }
        public function getConfig()
        {
            return new class {
                public function get($k)
                {
                    return null;
                }
            };
        }
        public function getParams()
        {
            return new \Joomla\Registry\Registry();
        }
    }
}

namespace Joomla\CMS\Document {
    class HtmlDocument
    {
        public function getLanguage()
        {
            return 'en-GB';
        }
        public function addHeadLink($a, $b, $c, $d = []) {}
        public function setMetaData($a, $b, $c = null) {}
        public function getHeadData()
        {
            return ['scripts' => [], 'custom' => [], 'metaTags' => []];
        }
        public function getTitle()
        {
            return '';
        }
        public function getDescription()
        {
            return '';
        }
        public function addCustomTag($t) {}
    }
}

namespace Joomla\CMS {
    class Factory
    {
        public static function getApplication()
        {
            return new \Joomla\CMS\Application\CMSApplication();
        }
        public static function getDocument()
        {
            return new \Joomla\CMS\Document\HtmlDocument();
        }
        public static function getDbo()
        {
            return new class {
                public function getQuery($b = false)
                {
                    return new class {
                        public function select($a)
                        {
                            return $this;
                        }
                        public function from($a)
                        {
                            return $this;
                        }
                        public function where($a)
                        {
                            return $this;
                        }
                        public function order($a)
                        {
                            return $this;
                        }
                        public function setLimit($a)
                        {
                            return $this;
                        }
                        public function join($a, $b)
                        {
                            return $this;
                        }
                        public function setLimitBy($a)
                        {
                            return $this;
                        }
                    };
                }
                public function setQuery($q) {}
                public function loadObject()
                {
                    return null;
                }
                public function loadObjectList()
                {
                    return [];
                }
                public function loadColumn()
                {
                    return [];
                }
                public function loadResult()
                {
                    return null;
                }
                public function quoteName($n)
                {
                    return (string)$n;
                }
                public function quote($n)
                {
                    return "'" . (string)$n . "'";
                }
            };
        }
        public static function getUser($id)
        {
            return (object)['name' => ''];
        }
        public static function getConfig()
        {
            return new class {
                public function get($k)
                {
                    return 'UTC';
                }
            };
        }
        public static function getLanguage()
        {
            return new class {
                public function getTag()
                {
                    return 'en-GB';
                }
            };
        }
        public static function getDate($time = 'now')
        {
            return new \Joomla\CMS\Date\Date($time);
        }
    }
}

namespace Joomla\CMS\Uri {
    class Uri
    {
        public static function root()
        {
            return 'https://example.com/';
        }
        public static function getInstance()
        {
            return new class {
                public function getPath()
                {
                    return '/';
                }
                public function getHost()
                {
                    return 'example.com';
                }
                public function getScheme()
                {
                    return 'https';
                }
                public function getPort()
                {
                    return 443;
                }
                public function toString()
                {
                    return 'https://example.com/';
                }
            };
        }
    }
}

namespace Joomla\CMS\Router {
    class Route
    {
        public static function _($a)
        {
            return (string)$a;
        }
    }
}

namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        public static function _(...$args) {}
    }
}

namespace Joomla\CMS\Language {
    class LanguageHelper
    {
        public static function getLanguages($a)
        {
            return [];
        }
    }
}

namespace {
    class JLanguageAssociations
    {
        public static function isEnabled()
        {
            return false;
        }
    }
}

namespace Joomla\CMS\Association {
    class AssociationHelper
    {
        public static function getAssociations($a, $b, $c, $d)
        {
            return [];
        }
    }
}

namespace Joomla\CMS\Date {
    class Date extends \DateTime
    {
        public function __construct($t = 'now', $tz = null)
        {
            parent::__construct(is_string($t) ? $t : 'now', $tz ?: new \DateTimeZone('UTC'));
        }
        public function toISO8601()
        {
            return $this->format(DATE_ATOM);
        }
    }
}

namespace {
    class JLog
    {
        public const DEBUG = 100;
        public static function add($message, $level = self::DEBUG, $category = 'default') {}
    }
}

namespace Joomla\CMS\MVC\Model {
    class BaseDatabaseModel
    {
        public static function addIncludePath($path) {}
    }
}

namespace Joomla\Component\Content\Site\Model {
    class ArticleModel
    {
        public function __construct($config = []) {}
        public function setState($key, $value) {}
        public function getItem($id)
        {
            return (object) [
                'id' => $id,
                'title' => 'Sample',
                'introtext' => '',
                'fulltext' => '',
                'created' => 'now',
                'modified' => 'now',
                'metadesc' => '',
                'metakey' => ''
            ];
        }
    }
    class CategoryModel
    {
        public function __construct($config = []) {}
        public function getCategory($id)
        {
            return (object) [
                'id' => $id,
                'title' => 'Category',
                'metadesc' => '',
                'description' => ''
            ];
        }
    }
}
