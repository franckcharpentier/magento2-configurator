<?php
namespace CtiDigital\Configurator\Model\Component;

use Magento\Authorization\Model\ResourceModel\Role;
use Symfony\Component\Yaml\Yaml;
use Magento\Authorization\Model\RoleFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Authorization\Model\RulesFactory;
use CtiDigital\Configurator\Model\LoggingInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use CtiDigital\Configurator\Model\Exception\ComponentException;

class AdminRoles extends YamlComponentAbstract
{
    protected $alias = 'adminroles';
    protected $name = 'Admin Roless';
    protected $description = 'Component to create Admin Roless';

    /**
     * RoleFactory
     *
     * @var roleFactory
     */
    private $roleFactory;

    /**
     * RulesFactory
     *
     * @var rulesFactory
     */
    private $rulesFactory;

    /**
     * AdminRoles constructor.
     * @param LoggingInterface $log
     * @param ObjectManagerInterface $objectManager
     * @param RoleFactory $roleFactory
     * @param RulesFactory $rulesFactory
     */
    public function __construct(
        LoggingInterface $log,
        ObjectManagerInterface $objectManager,
        $roleFactory,
        $rulesFactory
    ) {
        parent::__construct($log, $objectManager);

        $this->roleFactory = $roleFactory;
        $this->rulesFactory = $rulesFactory;
    }

    /**
     * @param array $data
     * @SuppressWarnings(PHPMD)
     */
    protected function processData($data = null)
    {

        if (isset($data['adminroles'])) {
            foreach ($data['adminroles'] as $role) {
                try {
                    if (isset($role['name'])) {
                        $role = $this->createAdminRole($role['name'], $role['resources']);
                    }
                } catch (ComponentException $e) {
                    $this->log->logError($e->getMessage());
                }
            }
        }
    }

    /**
     * Create Admin user roles, or update them if they exist
     *
     * @param $roleName
     * @param $resources
     */
    private function createAdminRole($roleName, $resources)
    {
        $role = $this->roleFactory->create();
        $roleCount = $role->getCollection()->addFieldToFilter('role_name', $roleName)->getSize();

        // Create or get existing user
        if ($roleCount > 0) {
            $this->log->logInfo(
                sprintf('Admin Role "%s" creation skipped: Already exists in database', $roleName)
            );

            //Get exisiting Role
            $role = $role->getCollection()->addFieldToFilter('role_name', $roleName)->getFirstItem();
            $this->setResourceIds($role, $resources);

            return;
        }

        $this->log->logInfo(
            sprintf('Admin Role "%s" being created', $roleName)
        );

        $role->setRoleName($roleName)
            ->setParentId(0)
            ->setRoleType(RoleGroup::ROLE_TYPE)
            ->setUserType(UserContextInterface::USER_TYPE_ADMIN)
            ->setSortOrder(0)
            ->save();

        $this->setResourceIds($role, $resources);
    }

    /**
     * Set ResourceIDs the Admin Role will have access to
     *
     * @param role
     * @param array|null $resources
     */
    private function setResourceIds($role, $resources)
    {
        $roleId = $role->getId();
        $roleName = $role->getRoleName();

        if ($resources !== null) {
            $this->log->logInfo(
                sprintf('Admin Role "%s" resources updating', $roleName)
            );

            $this->rulesFactory->create()->setRoleId($roleId)->setResources($resources)->saveRel();
            return;
        }

        $this->log->logError(
            sprintf('Admin Role "%s" Resources are empty, please check your yaml file', $roleName)
        );

    }
}
