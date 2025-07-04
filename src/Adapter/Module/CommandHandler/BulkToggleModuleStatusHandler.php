<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Module\CommandHandler;

use tools\profiling\Module;
use PrestaShop\PrestaShop\Core\CommandBus\Attributes\AsCommandHandler;
use PrestaShop\PrestaShop\Core\Domain\Module\Command\BulkToggleModuleStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Module\CommandHandler\BulkToggleModuleStatusHandlerInterface;
use PrestaShop\PrestaShop\Core\Domain\Module\Exception\ModuleNotInstalledException;
use PrestaShop\PrestaShop\Core\Module\ModuleManager;
use PrestaShop\PrestaShop\Core\Module\ModuleRepository;
use Psr\Log\LoggerInterface;

/**
 * Bulk toggles Module status
 */
#[AsCommandHandler]
class BulkToggleModuleStatusHandler implements BulkToggleModuleStatusHandlerInterface
{
    /**
     * @param ModuleManager $moduleManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly ModuleRepository $moduleRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(BulkToggleModuleStatusCommand $command): void
    {
        $modulesToUpdate = [];
        // First loop checks that the provided modules exist and don't need some update
        // If one module is not found the whole bulk is cancelled because an exception is thrown
        foreach ($command->getModules() as $moduleName) {
            $module = $this->moduleRepository->getPresentModule($moduleName);
            if (!$module->isInstalled()) {
                throw new ModuleNotInstalledException('Cannot toggle status for module ' . $moduleName . ' since it is not installed');
            }

            if ($this->isDisablingAlreadyDisabledModule($command->getExpectedStatus(), $moduleName)) {
                continue;
            }
            $modulesToUpdate[] = $moduleName;
        }

        // Now we can perform the toggle
        foreach ($modulesToUpdate as $moduleName) {
            if ($command->getExpectedStatus()) {
                if ($this->moduleManager->enable($moduleName)) {
                    $this->logger->warning(
                        sprintf(
                            'The module %s has been enabled',
                            $moduleName
                        )
                    );
                }
            } else {
                if ($this->moduleManager->disable($moduleName)) {
                    $this->logger->warning(
                        sprintf(
                            'The module %s has been disabled',
                            $moduleName
                        )
                    );
                }
            }
        }
    }

    private function isDisablingAlreadyDisabledModule(bool $expectedStatus, string $moduleName): bool
    {
        return !$expectedStatus && !$this->moduleManager->isInstalledAndActive($moduleName);
    }
}
