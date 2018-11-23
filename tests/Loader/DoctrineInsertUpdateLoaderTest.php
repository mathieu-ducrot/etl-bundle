<?php

namespace Smart\EtlBundle\Tests\Loader;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Smart\EtlBundle\Loader\DoctrineInsertUpdateLoader;
use Smart\EtlBundle\Tests\Entity\Project;
use Smart\EtlBundle\Tests\Entity\Task;

/**
 * vendor/bin/phpunit tests/Loader/DoctrineInsertUpdateLoaderTest.php
 *
 * @author Nicolas Bastien <nicolas.bastien@smartbooster.io>
 */
class DoctrineInsertUpdateLoaderTest extends WebTestCase
{
    public function testLoad()
    {
        //Initialise database
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getManager('default');
        $metadatas = $em->getMetadataFactory()->getMetadataFor('Smart\EtlBundle\Tests\Entity\Project');

        $schemaTool = new SchemaTool($em);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema([$metadatas]);
        
        $this->loadFixtureFiles([
            __DIR__ . '/../fixtures/doctrine-loader/project.yml',
            __DIR__ . '/../fixtures/doctrine-loader/task.yml',
        ]);

        $projectEtl = new Project('etl-bundle', 'ETL Bundle');
        $projectEtl->setDescription('new description updated');

        $loader = new DoctrineInsertUpdateLoader($em);
        $loader->addEntityToProcess(
            'Smart\EtlBundle\Tests\Entity\Project',
            function ($e) {
                return $e->getCode();
            },
            'code',
            [
                'code',
                'name',
                'description'
            ]
        );
        $loader->addEntityToProcess(
            'Smart\EtlBundle\Tests\Entity\Task',
            function ($e) {
                return $e->getCode();
            },
            'code',
            [
                'project',
                'code',
                'name'
            ]
        );

        $loader->load([$projectEtl]);

        /** @var Project $projectEtlLoaded */
        $projectEtlLoaded = $em->getRepository('Smart\EtlBundle\Tests\Entity\Project')->findOneBy([
            'code' => 'etl-bundle'
        ]);
        $this->assertEquals('new description updated', $projectEtlLoaded->getDescription());
        $this->assertEquals(2, $em->getRepository('Smart\EtlBundle\Tests\Entity\Project')->count([]));

        //Test Insertion
        $newProject = new Project('new-project', 'new project');
        $loader->load([$projectEtl, $newProject]);
        $this->assertEquals(3, $em->getRepository('Smart\EtlBundle\Tests\Entity\Project')->count([]));
        /** @var Project $projectEtlLoaded */
        $newProjectLoaded = $em->getRepository('Smart\EtlBundle\Tests\Entity\Project')->findOneBy([
            'code' => 'new-project'
        ]);
        $this->assertEquals('new project', $newProjectLoaded->getName());
        $newProject->setName('new project updated');
        $loader->load([$projectEtl, $newProject]);
        $this->assertEquals(3, $em->getRepository('Smart\EtlBundle\Tests\Entity\Project')->count([]));
        /** @var Project $projectEtlLoaded */
        $newProjectLoaded = $em->getRepository('Smart\EtlBundle\Tests\Entity\Project')->findOneBy([
            'code' => 'new-project'
        ]);
        $this->assertEquals('new project updated', $newProjectLoaded->getName());

        //=======================
        //  Test relations
        //=======================
        $this->assertEquals(2, $em->getRepository('Smart\EtlBundle\Tests\Entity\Task')->count([]));
        $taskSetUp = new Task($projectEtl, 'Bundle setup updated');
        $taskSetUp->setCode('etl-bundle-setup');

        $newTask = new Task($projectEtl, 'New Task');
        $newTask->setCode('etl-bundle-new-task');

        $loader->load([$taskSetUp, $newTask]);

        $this->assertEquals(3, $em->getRepository('Smart\EtlBundle\Tests\Entity\Task')->count([]));
        $newTaskLoaded = $em->getRepository('Smart\EtlBundle\Tests\Entity\Task')->findOneBy([
            'code' => 'etl-bundle-new-task'
        ]);
        $this->assertEquals('New Task', $newTaskLoaded->getName());

        $newTask->setName('New Task updated');
        $loader->load([$taskSetUp, $newTask]);
        $this->assertEquals(3, $em->getRepository('Smart\EtlBundle\Tests\Entity\Task')->count([]));
        $this->assertEquals('New Task updated', $newTaskLoaded->getName());
    }
}