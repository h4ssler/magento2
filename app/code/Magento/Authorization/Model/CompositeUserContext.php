<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Authorization\Model;

use Magento\Framework\ObjectManager\Helper\Composite as CompositeHelper;

/**
 * Composite user context (implements composite pattern).
 */
class CompositeUserContext implements \Magento\Authorization\Model\UserContextInterface
{
    /**
     * @var UserContextInterface[]
     */
    protected $userContexts = [];

    /**
     * @var UserContextInterface|bool
     */
    protected $chosenUserContext;

    /**
     * Register user contexts.
     *
     * @param CompositeHelper $compositeHelper
     * @param UserContextInterface[] $userContexts
     */
    public function __construct(CompositeHelper $compositeHelper, $userContexts = [])
    {
        $userContexts = $compositeHelper->filterAndSortDeclaredComponents($userContexts);

//        Cyrill: all contexts in their correct order:
//        string 'Magento\Webapi\Model\Authorization\TokenUserContext' (length=51)
//        string 'Magento\Customer\Model\Authorization\CustomerSessionUserContext' (length=63)
//        string 'Magento\User\Model\Authorization\AdminSessionUserContext' (length=56)
//        string 'Magento\Webapi\Model\Authorization\OauthUserContext' (length=51)
//        string 'Magento\Webapi\Model\Authorization\GuestUserContext' (length=51)
//        see then method getUserContext() which chooses the correct context.
//        a context can be removed from Magento/Webapi/etc/webapi_rest/di.xml to allow "more security"

        foreach ($userContexts as $userContext) {
            $this->add($userContext['type']);
        }
    }

    /**
     * Add user context.
     *
     * @param UserContextInterface $userContext
     * @return CompositeUserContext
     */
    protected function add(UserContextInterface $userContext)
    {
        $this->userContexts[] = $userContext;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserId()
    {
        return $this->getUserContext() ? $this->getUserContext()->getUserId() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserType()
    {
        return $this->getUserContext() ? $this->getUserContext()->getUserType() : null;
    }

    /**
     * Retrieve user context
     *
     * @return UserContextInterface|bool False if none of the registered user contexts can identify user type
     */
    protected function getUserContext()
    {
        if (is_null($this->chosenUserContext)) {
            /** @var UserContextInterface $userContext */
            foreach ($this->userContexts as $userContext) {
                if ($userContext->getUserType() && !is_null($userContext->getUserId())) {
                    $this->chosenUserContext = $userContext;
                    break;
                }
            }
            if (is_null($this->chosenUserContext)) {
                $this->chosenUserContext = false;
            }
        }
//        \Zend_Debug::dump(get_class($this->chosenUserContext));
//        exit;

        return $this->chosenUserContext;
    }
}
