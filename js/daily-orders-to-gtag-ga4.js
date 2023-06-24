async function sendOrdersTo_GA4(orders, transaction_id, seller_platform, currency = 'MYR') {
    return new Promise((resolve, reject) => {
        if (!orders) reject();
        if (!transaction_id) reject();
        if (!currency) reject();

        const items = orders.map(r => {
            const x = {};
            x.item_id = r.sku;
            x.item_name = r.description;
            x.price = parseFloat(r.sellingPrice);
            x.quantity = 1;

            x.seller_platform = seller_platform;

            return x;
        });
        const data = {};
        data.transaction_id = transaction_id;
        data.currency = currency;
        data.value = Math.round(
            items.map(function (x) { return x.quantity * x.price; })
                .reduce(function (cfx, x) { return cfx + x; }) * 100
        ) / 100;
        data.items = items;

        data.seller_platform = seller_platform;

        data.event_callback = resolve;
        gtag('event', 'purchase', data);
    });
}