<?php
require_once 'common.php';

$brands = array(
    'One',
    'Two',
    'Three',
    'Four',
    'Five',
    'Six',
    'Seven',
    'Eight',
    'Nine',
    'Ten'
);
$docs = array();
$start = 0;
$offset = 20;
$current = 1;
if (isset($_GET['start'])) {
    $start = $_GET['start'];
    $current = $start / $offset + 1;
}

// get the params
$search_query =$query = trim($_GET['query']);
$attrs = array(
    'categories',
    'brand_id',
    'price'
);
$where = array();

if (isset($_GET['categories'])) {
    $where[] = ' categories = ' . $_GET['categories'] . ' ';
}
if (isset($_GET['brand_id'])) {
    $where[] = ' brand_id = ' . $_GET['brand_id'] . ' ';
}

if (isset($_GET['price'])) {
    $where[] = ' price BETWEEN ' . ($_GET['price'] * 200) . ' AND ' . (($_GET['price'] + 1) * 200 - 1) . ' ';
}
if (isset($_GET['property'])) {
    $search_query .= ' @property ' . $_GET['property'];
}
if (count($where) > 0) {
    $where = ' AND ' . implode(' AND ', $where);
} else {
    $where = '';
}

$indexes = 'facetdemo';
// main search
$stmt = $ln_sph->prepare("SELECT * FROM $indexes WHERE MATCH(:match) $where  LIMIT $start,$offset ");
$stmt->bindValue(':match', $search_query, PDO::PARAM_STR);
$stmt->execute();
$docs = $stmt->fetchAll();

// get meta, to know the total found
$meta = $ln_sph->query("SHOW META")->fetchAll();
foreach ($meta as $m) {
    $meta_map[$m['Variable_name']] = $m['Value'];
}
$total_found = $meta_map['total_found'];
$total = $meta_map['total'];

// possible to be needed to enable multi-queries in PDO
$ln_sph->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);

$sql = array();
$rows = array();

// run optimized query, only numeric types
$sql[] = "SELECT *,GROUPBY() as selected,COUNT(*) as cnt FROM $indexes WHERE MATCH(:match) $where  GROUP BY categories ORDER BY cnt DESC LIMIT 0,10";
$sql[] = "SELECT *,GROUPBY() as selected,COUNT(*) as cnt FROM $indexes WHERE MATCH(:match) $where  GROUP BY brand_id ORDER BY cnt DESC LIMIT 0,10";
$sql = implode('; ', $sql);
$stmt = $ln_sph->prepare($sql);
$stmt->bindValue(':match', $search_query, PDO::PARAM_STR);
$stmt->execute();
foreach ($attrs as $attr) {
    $rows[$attr] = $stmt->fetchAll();
    $stmt->nextRowset();
}

// expressions are not yet supported in multi-query optimization,so we run them separate
$stmt = $ln_sph->prepare("SELECT *,GROUPBY() as selected,COUNT(*) as cnt, INTERVAL(price,200,400,600,800) as price_seg FROM
$indexes WHERE MATCH(:match) $where  GROUP BY price_seg ORDER BY cnt DESC LIMIT 0,10");
$stmt->bindValue(':match', $search_query, PDO::PARAM_STR);
$stmt->execute();
$prices = $stmt->fetchAll();

// string attrs are not yet supported in multi-query optimization, so we run them separate
$stmt = $ln_sph->prepare("SELECT *,COUNT(*) as cnt FROM $indexes WHERE MATCH(:match) $where GROUP BY property ORDER BY cnt DESC  LIMIT 0,10");
$stmt->bindValue(':match', $search_query, PDO::PARAM_STR);
$stmt->execute();
$property = $stmt->fetchAll();

// build the faceting arrays
$facets = array();
foreach ($property as $p) {
    $facets['property'][] = array(
        'value' => $p['property'],
        'count' => $p['cnt']
    );
}
foreach ($prices as $p) {
    $facets['price'][] = array(
        'value' => $p['price_seg'],
        'count' => $p['cnt']
    );
}
foreach ($rows as $k => $v) {
    foreach ($v as $x) {
        $facets[$k][] = array(
            'value' => $x['selected'],
            'count' => $x['cnt']
        );
    }
}
?>
<?php

$title = 'Demo simple autocomplete on title';
include 'template/header.php';
?>
<div class="container-fluid">
	<form method="GET" action="" id="search_form">
		<div class="row-fluid">
			<div class="span2">
				<br> <br> <br> <br>

				<fieldset>
					<legend>Brands</legend>
					<div class="control-group">
									<?php foreach($facets['brand_id'] as $item):?>
									<label class="radio"> <input type="radio" name="brand_id"
							value="<?=$item['value'];?>"
							<?=(isset($_GET['brand_id']) && ($_GET['brand_id'] == $item['value']))?'checked':'';?>>
										<?='Brand '.$brands[$item['value']-1].' ('.$item['count'].')'?>
									</label>
									<?php endforeach;?>
														<input type="button" name="reset_brand_id" value="Reset"
							data-target="brand_id" class="btn">
					</div>
				</fieldset>

				<fieldset>
					<legend>Categories</legend>
					<div class="control-group">
									<?php foreach($facets['categories'] as $item):?>
									<label class="radio"> <input type="radio" name="categories"
							value="<?=$item['value'];?>"
							<?=(isset($_GET['categories']) && ($_GET['categories'] == $item['value']))?'checked':'';?>>
										Category <?=$item['value'].' ('.$item['count'].')'?>
									</label>
									<?php endforeach;?>
                                    <input type="button"
							name="reset_categories" value="Reset" data-target="categories"
							class="btn">
					</div>
				</fieldset>

				<fieldset>
					<legend>Price</legend>
					<div class="control-group">
									<?php foreach($facets['price'] as $item):?>
									<label class="radio"> <input type="radio" name="price"
							value="<?=$item['value'];?>"
							<?=(isset($_GET['price']) && ($_GET['price'] == $item['value']))?'checked':'';?>>
										<?=($item['value']*200).'-'.(($item['value']+1)*200).' ('.$item['count'].')'?>
									</label>
									<?php endforeach;?>
                                    <input type="button"
							name="reset_price" value="Reset" data-target="price" class="btn">
					</div>

					<fieldset>
						<legend>Property</legend>
						<div class="control-group">
										<?php foreach($facets['property'] as $item):?>
										<label class="radio"> <input type="radio" name="property"
								value="<?=$item['value'];?>"
								<?=(isset($_GET['property']) && ($_GET['property'] == $item['value']))?'checked':'';?>>
											<?=$item['value'].' ('.$item['count'].')'?>
										</label>
										<?php endforeach;?>
                                        <input type="button"
								name="reset_property" value="Reset" data-target="property"
								class="btn">
						</div>
					</fieldset>
			
			</div>
			<div class="span9">
				<div class="container">
					<ul class="nav nav-pills">
						<ul class="nav nav-pills">
							<li><a href="index.php">Simple Faceting</a></li>
							<li class="active"><a href="facetprices.php">Segmented price (
									old style )</a></li>
							<li><a href="facetpricesnew.php">Segmented price ( FACET )</a></li>
							<li><a href="facetmulti.php">Facets with multiple selection</a></li>
						</ul>
					</ul>
					<header>
						<h1>Facets with multiple selection</h1>
					</header>
					<div class="row">
						<div class="span9">

							<div class="well form-search">
								<input type="text" class="input-large" name="query" id="suggest"
									autocomplete="off"
									value="<?=isset($_GET['query'])?htmlentities($_GET['query']):''?>">
								<input type="submit" class="btn btn-primary" id="send"
									name="send" value="Submit">
								<button type="reset" class="btn " value="Reset">Reset</button>
							</div>
						</div>
					</div>
					<div class="row">
						<?php if (count($docs) > 0): ?>
						<p class="lead">
							Total found:<?=$total_found?>
						</p>
						<div class="span9"><?php include 'template/paginator.php';?></div>
						<div class="span9">
							<table class="table">
								<tr>
									<th>Title</th>
									<th>Categories</th>
									<th>Price</th>
									<th>Property</th>
									<th>Brand</th>
								</tr>
						  <?php foreach ($docs as $doc): ?>
						  <tr>
									<td><?= $doc['title']?></td>
									<td><?= $doc['categories'] ?></td>
									<td><?= $doc['price'] ?></td>
									<td><?= $doc['property'] ?></td>
									<td><?= $brands[$doc['brand_id']-1]  ?></td>
								</tr>
						  <?php endforeach; ?>
						  </table>
						</div>
						<div class="span9"><?php include 'template/paginator.php';?></div>
						<?php elseif (isset($_GET['query']) && $_GET['query'] != ''): ?>
						<p class="lead">Nothing found!</p>
						<?php endif; ?>
					</div>
	
	</form>
	<?php
include 'template/footer.php';
?>