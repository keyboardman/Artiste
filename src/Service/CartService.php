<?php

namespace App\Service;

use App\Entity\Article;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private const SESSION_KEY = 'cart';

    public function __construct(private RequestStack $requestStack) {}

    public function add(Article $article): void
    {
        $cart = $this->getCartRaw();
        $id = $article->getId();

        if (isset($cart[$id])) {
            $cart[$id]['quantity']++;
        } else {
            $cart[$id] = [
                'id'       => $id,
                'name'     => $article->getTitle(),
                'image'    => $article->getImage(),
                'price'    => $article->getPrice(),
                'quantity' => 1,
            ];
        }

        $this->save($cart);
    }

    public function remove(int $id): void
    {
        $cart = $this->getCartRaw();
        unset($cart[$id]);
        $this->save($cart);
    }

    public function getCart(): array
    {
        $cart = $this->getCartRaw();

        $total = array_reduce($cart, fn($sum, $item) => $sum + ($item['price'] * $item['quantity']), 0);

        return [
            'items' => array_values($cart),
            'total' => $total,
            'count' => array_sum(array_column($cart, 'quantity')),
        ];
    }

    public function clear(): void
    {
        $this->save([]);
    }

    private function getCartRaw(): array
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, []);
    }

    private function save(array $cart): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $cart);
    }
}
