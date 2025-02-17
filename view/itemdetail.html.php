<!DOCTYPE html>
<html lang="en">

<head>
	<?php require('view/template/head.php') ?>
	<link rel="stylesheet" href="js/table-cd-editable-sheet/table-cd-editable-sheet.css">
	<script type="module" src="js/table-cd-editable-sheet/table-cd-editable-sheet.js" async defer></script>
</head>


<?php
$item = $handler->getItem($_GET['id']);
?>

<body>
	<?php include('inc/html/nav.html'); ?>
	<div class="paper">
		<h2>item detail</h2>
		<form method="post" action="#" cd-editable-sheet>
			<input type="hidden" name="request" value="stock_item.update">
			<input type="hidden" name="id" value="<?= text($item['id']); ?>">

			<table cd-editable-sheet>
				<thead>
					<tr>
						<th>Field</th>
						<th>Value</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Item Code</td>
						<td><input type="text" name="item_code" value="<?= text($item['item_code']); ?>" required readonly></td>
					</tr>
					<tr>
						<td>Description</td>
						<td><input type="text" name="description" value="<?= text($item['description']); ?>" required readonly></td>
					</tr>
					<tr>
						<td>UOM</td>
						<td><input type="text" name="uom" value="<?= text($item['uom']); ?>" readonly></td>
					</tr>
					<tr>
						<td>Item Group</td>
						<td><input type="text" name="item_group" value="<?= text($item['item_group']); ?>" readonly></td>
					</tr>
				</tbody>
			</table>
			<hr>
			<button type="submit">Save</button>
		</form>


	</div>

	<div class="paper">
		<style>
			.title>code {
				display: inline-block;
				padding: 0.5em;
			}
		</style>
		<h3 class="title">
			<code><?= text($item['item_code']); ?></code>
			<code><?= text($item['description']); ?></code>
		</h3>
		<form method="post" id="form-bigsellerSku" action="#" cd-ajax>
			<input type="hidden" name="item_id" value="<?= text($item['id']); ?>">

			<button type="button" class="btnAddRow">Add</button>
			<table cd-editable-sheet>
				<thead>
					<tr>
						<th>#</th>
						<th>Big Seller SKU</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($handler->getBigSellerSku($item['id']) as $b) : ?>
						<?php
						$id = text($b['id']);
						?>
						<tr>
							<td><input type="checkbox" name="<?= "x[$id][_enable]" ?>" title="<?= $id ?>"></td>
							<td><input type="text" name="<?= "x[$id][bigseller_sku]" ?>" value="<?= text($b['bigseller_sku']); ?>" maxlength="255"></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<hr>
			<button type="submit" name="request" value="bigseller_sku_map.update">Save</button>
			<button type="submit" name="request" value="bigseller_sku_map.remove">Remove</button>
		</form>

		<template id="template-bigsellerSku">
			<tr>
				<td><input type="checkbox" name="added[ ${i} ][_enable]"></td>
				<td><input type="text" name="added[ ${i} ][bigseller_sku]" value="" maxlength="255"></td>
			</tr>
		</template>
		<script>
			// #form-bigsellerSku .btnAddRow will add more row
			{
				let i = 0;
				document.querySelector('#form-bigsellerSku .btnAddRow').addEventListener('click', function(e) {
					const template = document.querySelector('#template-bigsellerSku');
					const tr = document.importNode(template.content, true);
					tr.querySelectorAll('input').forEach(function(input) {
						const name = input.getAttribute('name').replace('${i}', i);
						input.setAttribute('name', name);
					});
					++i;
					const tbody = document.querySelector('#form-bigsellerSku table tbody');
					tbody.appendChild(tr);
				});
			}

			document.querySelector('#form-bigsellerSku').addEventListener('input', function(e) {
				// auto check the checkbox
				if (e.target.tagName === 'INPUT' && e.target.type === 'text') {
					const tr = e.target.closest('tr');
					const checkbox = tr.querySelector('input[type="checkbox"]');
					checkbox.checked = e.target.value.trim() !== '';
				}
			});
		</script>
	</div>

	<script>
		document.body.addEventListener('submit', function(e) {
			if (confirm('Confirm changes?') !== true) {
				console.log(e.target);
				e.preventDefault();
				e.stopPropagation();
				return;
			}
		});
	</script>

	<!-- 
<form name="form_uploadItem_image" method="POST" action="process/uploadStock_image" enctype="multipart/form-data">
 <input type="hidden" value="x" name="item_id">

	<div class="paper">
	Upload new image for this product item.<br>
		<input type="file" name="fileImage" accept="image/*" onchange="readURL(this);" required> <br><br>
		<input type="submit" name="submit" /> <br>
		<span id="newimage_container" style="display: none;">
			<label for="fileDescription">Image Description: </label>
			<input type="text" id="fileDescription" name="fileDescription" placeholder="image description..." maxlength="255" /> <br /><br />
			<img src="" id="newimage" />
		</span>
	</div>

</form> -->


</body>

</html>