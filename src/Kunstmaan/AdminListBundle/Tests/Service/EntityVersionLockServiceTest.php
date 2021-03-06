<?php

namespace Kunstmaan\AdminListBundle\Tests\Service;

use Kunstmaan\AdminBundle\Entity\User;
use Kunstmaan\AdminListBundle\Entity\EntityVersionLock;
use Kunstmaan\AdminListBundle\Entity\LockableEntity;
use Kunstmaan\AdminListBundle\Service\EntityVersionLockService;
use Kunstmaan\AdminListBundle\Tests\Model\TestLockableEntityInterfaceImplementation;

/**
 * class EntityVersionLockServiceTest
 */
class EntityVersionLockServiceTest extends \PHPUnit_Framework_TestCase
{
    protected static $TEST_CLASS = "Kunstmaan\\AdminListBundle\\Tests\\Model\\TestLockableEntityInterfaceImplementation";
    protected static $TEST_ENTITY_ID = "5";
    protected static $ALTERNATIVE_TEST_ENTITY_ID = "391";
    protected static $USER_ID = "104";
    protected static $USER_NAME = "Kevin Test";
    protected static $ALTERNATIVE_USER = "Alternative Test";
    protected static $THRESHOLD = 35;

    /**
     * @var EntityVersionLockService
     */
    protected $object;

    /**
     * @var User
     */
    protected $user;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $user = new User();
        $user->setId(self::$USER_ID);
        $user->setUsername(self::$USER_NAME);
        $this->user = $user;

        $entity = new LockableEntity();
        $entity->setEntityClass(self::$TEST_CLASS);
        $entity->setEntityId(self::$TEST_ENTITY_ID);
        $entity->setUpdated(new \DateTime());

        $outDatedEntity = new LockableEntity();
        $outDatedEntity->setEntityClass(self::$TEST_CLASS);
        $outDatedEntity->setEntityId(self::$ALTERNATIVE_TEST_ENTITY_ID);
        $outDatedEntity->setUpdated(new \DateTime("-1 days"));

        $entityVersionLock = new EntityVersionLock();
        $entityVersionLock->setOwner(self::$ALTERNATIVE_USER);
        $entityVersionLock->setLockableEntity($entity);
        $entityVersionLock->setCreatedAt(new \DateTime());

        $expiredEntityVersionLock = new EntityVersionLock();
        $expiredEntityVersionLock->setOwner($user->getUsername());
        $expiredEntityVersionLock->setLockableEntity($entity);
        $expiredEntityVersionLock->setCreatedAt(new \DateTime('-1 days'));

        $locksMap = [
            [$entity, self::$THRESHOLD, $this->user, [$entityVersionLock]],
            [$outDatedEntity, self::$THRESHOLD, $this->user, []],
        ];
        $mockLockRepository = $this->getMockBuilder('Kunstmaan\AdminListBundle\Repository\EntityVersionLockRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $mockLockRepository
            ->expects($this->any())
            ->method('findOneBy')
            ->will($this->returnValue($entityVersionLock));
        $mockLockRepository
            ->expects($this->any())
            ->method('getExpiredLocks')
            ->will($this->returnValue([$expiredEntityVersionLock]));
        $mockLockRepository
            ->expects($this->any())
            ->method('getLocksForLockableEntity')
            ->will($this->returnValueMap($locksMap));

        $lockableMap = [
            [self::$TEST_ENTITY_ID, self::$TEST_CLASS, $entity],
            [self::$ALTERNATIVE_TEST_ENTITY_ID, self::$TEST_CLASS, $outDatedEntity],
        ];
        $mockLockableRepository = $this->getMockBuilder('Kunstmaan\AdminListBundle\Repository\LockableEntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $mockLockableRepository
            ->expects($this->any())
            ->method('getOrCreate')
            ->will($this->returnValueMap($lockableMap));

        $repositoryMap = [
            ['KunstmaanAdminListBundle:EntityVersionLock', $mockLockRepository],
            ['KunstmaanAdminListBundle:LockableEntity', $mockLockableRepository]
        ];
        $mockObjectManager = $this->getMockBuilder('Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();
        $mockObjectManager
            ->expects($this->any())
            ->method('getRepository')
            ->will($this->returnValueMap($repositoryMap));

        $this->object = new EntityVersionLockService($mockObjectManager, self::$THRESHOLD, true);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function testIsEntityBelowThresholdReturnsTrueWhenEntityUpdatedAtIsBelowThreshold()
    {
        $result = $this->object->isEntityBelowThreshold(new TestLockableEntityInterfaceImplementation(self::$TEST_ENTITY_ID));

        $this->assertTrue($result);
    }

    public function testIsEntityBelowThresholdReturnsFalseWhenEntityUpdatedAtIsOverTreshold()
    {

        $result = $this->object->isEntityBelowThreshold(new TestLockableEntityInterfaceImplementation(self::$ALTERNATIVE_TEST_ENTITY_ID));

        $this->assertFalse($result);
    }

    public function testIsEntityLockedReturnsTrueWhenEntityLocked()
    {
        $result = $this->object->isEntityLocked($this->user, new TestLockableEntityInterfaceImplementation(self::$TEST_ENTITY_ID));

        $this->assertTrue($result);
    }

    public function testIsEntityLockedReturnsFalseWhenEntityIsNotLocked()
    {
        $result = $this->object->isEntityLocked($this->user, new TestLockableEntityInterfaceImplementation(self::$ALTERNATIVE_TEST_ENTITY_ID));

        $this->assertFalse($result);
    }

    public function testGetUsersWithEntityVersionLockReturnsArrayWithOnlyUsernames()
    {
        $result = $this->object->getUsersWithEntityVersionLock(new TestLockableEntityInterfaceImplementation(self::$TEST_ENTITY_ID), $this->user);

        $this->assertContains(self::$ALTERNATIVE_USER, $result);
    }
}
