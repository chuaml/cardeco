<?php 
require_once(__DIR__ . '/../vendor/autoload.php');

$Shopee = new Shopee_Orders($_FILES['file_dataFile']);
$Stock_Getter = new Stock_Items_Getter();
$Checker = new Stock_Items_Checker(new Shopee_Product_Info);
$Formatter = null;
$Conclusion = new Stock_Items_Conclusion(new Shopee_Product_Info);



try{
	$Shopee->gatherItems_Code();
	$items_code = $Shopee->getItems_Code();
	
	
	$orders_list = $Shopee->getOrders_List();

	$Stock_Getter->setItems_Code($items_code);
	$stock_items = $Stock_Getter->getStock_Items($con);


	$Checker->setItems_Code($orders_list);
	$Checker->setResult($stock_items);

	$Conclusion->setOrders_List($orders_list);
	$Conclusion->setResult($stock_items);


	$output = $Checker->getOutput();


$Formatter = new Orders_Result_Formatter($output);


}catch(Exception $e){
	echo 'Error, no result matched.';
	throw $e;
}
