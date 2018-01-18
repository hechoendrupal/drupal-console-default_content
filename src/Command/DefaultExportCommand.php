<?php

/**
 * @file
 * Contains Drupal\Console\default_content\Command\DefaultExportCommand.
 */

namespace Drupal\Console\default_content\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\Command;
use Drupal\Console\Core\Command\Shared\CommandTrait;
use Drupal\Console\Command\Shared\ModuleTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Symfony\Component\Filesystem\Filesystem;
use Drupal\Console\Extension\Manager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Console\Core\Utils\StringConverter;
use Drupal\default_content\Exporter;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Console\Annotations\DrupalCommand;

/**
 * @DrupalCommand(
 *     extension = "default_content",
 *     extensionType = "module"
 * )
 */
class DefaultExportCommand extends Command
{

    use CommandTrait;
    use ModuleTrait;

    /**
     * @var Manager
     */
    protected $extensionManager;

    /**
     * The entity manager.
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
     * @var EntityTypeRepositoryInterface
     */
    protected $entityRepository;

    /**
     * @var Exporter
     */
    protected $exporter;

    /**
     * DefaultExportCommand constructor.
     * @param Manager $extensionManager
     * @param EntityTypeManagerInterface $entityManager
     * @param StringConverter $stringConverter
     * @param QueryFactory $entityQuery
     * @param EntityTypeRepositoryInterface $entityRepository
     * @param DefaultContentManager $defaultContentManger
     */
    public function __construct(Manager $extensionManager, EntityTypeManagerInterface $entityManager, StringConverter $stringConverter, QueryFactory $entityQuery, EntityTypeRepositoryInterface $entityRepository, Exporter $exporter)
    {
        $this->extensionManager = $extensionManager;
        $this->entityManager = $entityManager;
        $this->stringConverter = $stringConverter;
        $this->entityQuery = $entityQuery;
        $this->entityRepository = $entityRepository;
        $this->exporter = $exporter;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('default:content:export')
            ->setDescription($this->trans('commands.default.content.export.description'))
            ->addArgument(
                'entity-type',
                InputArgument::REQUIRED,
                $this->trans('commands.default.content.export.arguments.entity-type')
            )
            ->addArgument(
                'entity-id',
                InputArgument::REQUIRED,
                $this->trans('commands.default.content.export.arguments.entity-id')
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

        $entityType = $input->getArgument('entity-type');
        $entityId = $input->getArgument('entity-id');
        $module = $input->getOption('module');

        if (!$module) {
            $io->warning($this->trans('commands.default.content.export.messages.module-not-found'));
            return;
        }

        $entityStorage = $this->entityManager->getStorage($entityType);
        if (strtolower($entityId) == 'all') {
            $entities = $entityStorage->loadMultiple();
            $entityIds = array_keys($entities);
        } else {
            $entityIds = array($entityId);
        }

        if (!empty($entityIds)) {
            foreach ($entityIds as $entityId) {
                $entity = $entityStorage->load($entityId);

                if ($entity) {

                    $export = $this->exporter->exportContent($entityType, $entityId);
                    $entityFileName = $this->stringConverter->createMachineName($entity->id()) . '.json';
                    $entityExportFile = $this->extensionManager->getModule($module)->getPath() . '/content/' . $entityType . '/' . $entityFileName;

                    $fileSystem->dumpFile($entityExportFile, $export);

                    $io->info(
                        sprintf(
                            $this->trans('commands.default.content.export.messages.entity-exported'),
                            $entityType,
                            $entityId,
                            $entityExportFile
                        )
                    );

                } else {
                    $io->warning(
                        sprintf(
                            $this->trans('commands.default.content.export.messages.entity-not-found'),
                            $entityType,
                            $entityId
                        )
                    );
                }
            }
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

        $entitytypes = $this->entityRepository->getEntityTypeLabels(TRUE);

        $entityType = $input->getArgument('entity-type');
        if (!$entityType) {
            $entityType = $io->choiceNoList(
                $this->trans('commands.default.content.export.questions.entity-type'),
                array_keys($entitytypes['Content']),
                ''
            );
            $input->setArgument('entity-type', $entityType);
        }

        $entityID = $input->getArgument('entity-id');
        if (!$entityID) {
            $entityID = $io->ask(
                $this->trans('commands.default.content.export.questions.entity-id')
            );
            $input->setArgument('entity-id', $entityID);
        }
    }

}