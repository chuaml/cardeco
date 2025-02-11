<?php

final class Stock_Item
{
    private mysqli $con;
    public function __construct(
        mysqli $con
    ) {
        $this->con = $con;
    }

    public function getItem(int $id): array
    {
        // select everything from `stock_items` table by `id`
        $stmt = $this->con->prepare(
            'SELECT * FROM stock_items WHERE id = ?;'
        );
        try {
            // get the stmt result
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            return $result[0];
        } finally {
            $stmt->close();
        }
    }

    public function handleRequest(array $post): void
    {
        if (empty($post)) {
            return;
        }
        $action = $post['request'];
        switch ($action) {
            case 'stock_item.update':
                $this->updateStockItem($post);
                break;
            case 'bigseller_sku_map.update':
                $list = $this->filterForSelectedOnly($post['added'] ?? []);
                $this->addBigSellerSku($list, $post['item_id']);
                $list = $this->filterForSelectedOnly($post['x'] ?? []);
                $this->updateBigSellerSku($list);
                break;
            case 'bigseller_sku_map.remove':
                $list = $this->filterForSelectedOnly($post['x'] ?? []);
                $this->removeBigSellerSku($list);
                break;
            default:
                break;
        }
    }

    public function filterForSelectedOnly(array $list): array
    {
        return array_filter(
            $list,
            function ($x) {
                return isset($x['_enable']) === true && $x['_enable'] === 'on';
            }
        );
    }

    public function updateStockItem(array $post): void
    {
        // update `stock_items` table by `id`
        $stmt = $this->con->prepare(
            'UPDATE stock_items SET item_code = ?, description = ?, uom = ?, item_group = ? WHERE id = ?;'
        );
        try {
            $stmt->bind_param(
                'ssssi',
                $post['item_code'],
                $post['description'],
                $post['uom'],
                $post['item_group'],
                $post['id']
            );
            $stmt->execute();
        } finally {
            $stmt->close();
        }
    }

    public function getBigSellerSku(int $item_id): array
    {
        $stmt = $this->con->prepare(
            'SELECT b.id, b.bigseller_sku, b.item_id FROM bigseller_sku_map b INNER JOIN stock_items si on si.id = b.item_id WHERE si.id = ?;'
        );
        try {
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } finally {
            $stmt->close();
        }
    }

    public function addBigSellerSku(array $list, int $item_id): void
    {
        if (empty($list)) {
            return;
        }

        $stmt = $this->con->prepare(
            'INSERT INTO bigseller_sku_map (bigseller_sku, item_id) VALUES (?, ?);'
        );
        try {
            foreach ($list as $x) {
                $stmt->bind_param(
                    'si',
                    $x['bigseller_sku'],
                    $item_id
                );
                $stmt->execute();
            }
        } finally {
            $stmt->close();
        }
    }

    public function updateBigSellerSku(array $list): void
    {
        if (empty($list)) {
            return;
        }
        $stmt = $this->con->prepare(
            'UPDATE bigseller_sku_map SET bigseller_sku = ? WHERE id = ?;'
        );
        try {
            foreach ($list as $id => $x) {
                $stmt->bind_param(
                    'si',
                    $x['bigseller_sku'],
                    $id
                );
                $stmt->execute();
            }
        } finally {
            $stmt->close();
        }
    }

    public function removeBigSellerSku(array $list): void
    {
        $stmt = $this->con->prepare(
            'DELETE FROM bigseller_sku_map WHERE id = ?;'
        );
        try {
            foreach ($list as $id => $x) {
                if ($x['_enable'] !== 'on') {
                    continue;
                }

                $stmt->bind_param('i', $id);
                $stmt->execute();
            }
        } finally {
            $stmt->close();
        }
    }
}
