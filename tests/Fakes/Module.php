<?php

declare(strict_types=1);

if (!class_exists('Module', false)) {
    class Module
    {
        /** @var array<string, bool> */
        public static array $enabled = [];

        /** @var array<string, Module> */
        public static array $instances = [];

        // Simulates a provider whose real-world construction (PrestaShop's
        // coreLoadModule(), triggered by getInstanceByName()) throws — e.g. a partial
        // deploy missing the provider's scoped vendor/autoload.php. Left null, behaviour
        // is unchanged from before this property existed.
        public static ?Throwable $throwOnGetInstance = null;

        public string $name = '';

        /** @var list<string> */
        public array $registeredHooks = [];

        /** @var list<string> hooks whose registration fails */
        public array $failHooks = [];

        public static function reset(): void
        {
            self::$enabled = [];
            self::$instances = [];
            self::$throwOnGetInstance = null;
        }

        public static function isEnabled(string $moduleName): bool
        {
            return self::$enabled[$moduleName] ?? false;
        }

        /** @return Module|false */
        public static function getInstanceByName(string $moduleName)
        {
            if (null !== self::$throwOnGetInstance) {
                throw self::$throwOnGetInstance;
            }

            return self::$instances[$moduleName] ?? false;
        }

        /** @param string|array<int, string> $hookName */
        public function registerHook($hookName): bool
        {
            foreach ((array) $hookName as $hook) {
                if (in_array($hook, $this->failHooks, true)) {
                    return false;
                }
                $this->registeredHooks[] = $hook;
            }

            return true;
        }
    }
}
