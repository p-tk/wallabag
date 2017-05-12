<?php

namespace Wallabag\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;

class NotificationRepository extends EntityRepository {

    public function markAllAsReadForUser($userId) {
        return $this->getEntityManager()->createQueryBuilder()
        ->update('WallabagCoreBundle:Notification', 'n')
        ->set('n.read', true)
        ->where('n.user = :userId')->setParameter('userId', $userId)
        ->getQuery()
        ->getResult();
    }
}
