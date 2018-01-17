<?php

/**
 * @file
 */

namespace Drupal\default_content\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Drupal\Console\Core\Command\Shared\CommandTrait;
use Drupal\Console\Command\Shared\ModuleTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Symfony\Component\Filesystem\Filesystem;
use Drupal\system\Entity\Menu;
use Drupal\Console\Extension\Manager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Console\Core\Utils\StringConverter;
use Drupal\default_content\Exporter;
use Drupal\Core\Entity\Query\QueryFactory;

/**
 * Class DefaultExportMenuCommand.
 *
 * @package Drupal\default_content
 */
class DefaultExportMenuCommand extends Command
{

    use CommandTrait;
    use ModuleTrait;

    /**
     * @var Manager
     */
    protected $extensionManager;

    /**
     * The entity manager.
     *
     * @var EntityTypeManagerInterface
     */
    protected $entityManager;

    /**
     * @var StringConverter
     */
    protected $stringConverter;

    /**
     * @var QueryFactory
     */
    protected $entityQuery;

    /**
     * @var Exporter
     */
    protected $exporter;


    /**
     * DefaultExportMenuCommand constructor.
     * @param Manager $extensionManager
     * @param EntityTypeManagerInterface $entityManager
     * @param StringConverter $stringConverter
     * @param QueryFactory $entityQuery
     * @param Exporter $exporter
     */
    public function __construct(Manager $extensionManager, EntityTypeManagerInterface $entityManager, StringConverter $stringConverter, QueryFactory $entityQuery, Exporter $exporter)
    {
        $this->extensionManager = $extensionManager;
        $this->entityManager = $entityManager;
        $this->stringConverter = $stringConverter;
        $this->entityQuery = $entityQuery;
        $this->exporter = $exporter;
        parent::__construct();
    }


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('default:content:export:menu')
            ->setDescription($this->trans('commands.default.content.export.menu.description'))
            ->addArgument(
                'parent-menu',
                InputArgument::REQUIRED,
                $this->trans('commands.default.content.export.menu.arguments.parent-menu')
            )->addOption(
                'module',
                '',
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.common.options.module')
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $io = new DrupalStyle($input, $output);
        $fileSystem = new Filesystem();

        $parentMenu = $input->getArgument('parent-menu');
        $module = $input->getOption('module');

        if (!$module) {
            $io->warning($this->trans('commands.default.content.export.messages.module-not-found'));
            return;
        }


        $menuStorage = $this->entityManager->getStorage('menu_link_content');;

        if (!empty($menuStorage)) {
            $query = \Drupal::entityQuery('menu_link_content')
                ->condition('menu_name', $parentMenu)
                // Order by weight so as to be helpful for menus that are only one level
                // deep.
                ->sort('weight');
            $menu_links = $query->execute();

            foreach ($menu_links as $menu) {

                $entity = $menuStorage->load($menu);
                $export = $this->exporter->exportContent('menu_link_content', $menu);
                $entityFileName = $this->stringConverter->createMachineName($entity->label()) . '.json';
                $entityExportFile = $this->extensionManager->getModule($module)->getPath() . '/content/' . 'menu_link_content' . '/' . $entityFileName;

                $fileSystem->dumpFile($entityExportFile, $export);

                $io->info(
                    sprintf(
                        $this->trans('commands.default.content.export.messages.entity-exported'),
                        'menu_link_content',
                        $module
                    )
                );
            }
        } else {
            $io->warning(
                sprintf(
                    $this->trans('commands.default.content.export.messages.entity-not-found'),
                    $parentMenu
                )
            );
        }


    }


    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {

        $io = new DrupalStyle($input, $output);

        // --module option.
        $module = $input->getOption('module');
        if (!$module) {
            // @see Drupal\Console\Command\Shared\ModuleTrait::moduleQuestion
            $module = $this->moduleQuestion($io);
        }
        $input->setOption('module', $module);

        $all_menus = Menu::loadMultiple();
        $menus = array();
        foreach ($all_menus as $id => $menu) {
            $menus[$id] = $menu->label();
        }
        asort($menus);

        $entityType = $input->getArgument('parent-menu');
        if (!$entityType) {
            $entityType = $io->choiceNoList(
                $this->trans('commands.default.content.export.menu.questions.parent-menu'),
                array_keys($menus),
                ''
            );
        }
        $input->setArgument('parent-menu', $entityType);
    }

}