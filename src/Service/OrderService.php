<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CartService $cart,
        private MailerService $mailer,
    ) {}

    public function createFromCart(User $user, OrderStatus $status = OrderStatus::Paid): ?Order
    {
        $cart = $this->cart->getCart();

        if (empty($cart['items'])) {
            return null;
        }

        $order = new Order();
        $order->setUser($user);
        $order->setStatus($status);
        $order->setReference(strtoupper(bin2hex(random_bytes(4))));

        $total = 0.0;

        /** @var array<int, array{artist: User, items: OrderItem[]}> $byArtist */
        $byArtist = [];

        foreach ($cart['items'] as $line) {
            $item = new OrderItem();
            $item->setTitle((string) ($line['name'] ?? ''));
            $item->setImage(isset($line['image']) ? (string) $line['image'] : null);
            $item->setUnitPrice(number_format((float) ($line['price'] ?? 0), 2, '.', ''));
            $item->setQuantity((int) ($line['quantity'] ?? 1));

            if (!empty($line['id'])) {
                $article = $this->em->getRepository(Article::class)->find((int) $line['id']);
                if ($article) {
                    $item->setArticle($article);

                    $stock = $article->getStock();
                    if ($stock !== null) {
                        $article->setStock(max(0, $stock - $item->getQuantity()));
                    }

                    $artist = $article->getUser();
                    if ($artist !== null) {
                        $artistId = $artist->getId();
                        $byArtist[$artistId] ??= ['artist' => $artist, 'items' => []];
                        $byArtist[$artistId]['items'][] = $item;
                    }
                }
            }

            $order->addItem($item);
            $total += (float) $item->getUnitPrice() * $item->getQuantity();
        }

        $order->setTotal(number_format($total, 2, '.', ''));

        $this->em->persist($order);
        $this->em->flush();

        $this->cart->clear();

        try {
            $this->mailer->sendOrderConfirmationToBuyer($order);
        } catch (\Throwable) {
        }

        foreach ($byArtist as $group) {
            try {
                $this->mailer->sendOrderNotificationToArtist($group['artist'], $order, $group['items']);
            } catch (\Throwable) {
            }
        }

        return $order;
    }
}
