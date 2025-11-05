<?php include 'admin/db_connect.php' ?>

<div id="ab-status" class="mb-3">
    <?php
    $qry = $conn->query("SELECT * from status");
    ?>
    <?php while ($row = $qry->fetch_array()) : ?>
        <button type="button" class="btn btn-outline-success type-btn" style="background-color: <?= $row['color'] ?>; color:black;width:150px;height:50px;" data-id="<?= $row['id'] ?>"><?= $row['type']?></button>
    <?php endwhile; ?>
</div>

<?php

$transaction_data = json_encode([
    'transaction_id' => isset($_POST['transaction_id']) ? $_POST['transaction_id'] : null,
    'transaction_name' => isset($_POST['transaction_name']) ? $_POST['transaction_name'] : null,
    'transaction_symbol' => isset($_POST['transaction_symbol']) ? $_POST['transaction_symbol'] : null,
    'numberfrom' => isset($_POST['transaction_numberfrom']) ? $_POST['transaction_numberfrom'] : null,
    'numberto' => isset($_POST['transaction_numberto']) ? $_POST['transaction_numberto'] : null,
]);

?>
<script>
    $('.type-btn').click(function() {
        var transaction_info = <?php echo $transaction_data; ?>;
        var data = {
			transaction_id: transaction_info.transaction_id,
            transaction_name: transaction_info.transaction_name,
			transaction_symbol: transaction_info.transaction_symbol,
			transaction_numberfrom: transaction_info.numberfrom,
			transaction_numberto: transaction_info.numberto,
            type_id:$(this).data('id'),
            to:'doctor'
		};
		$('#show_modal').modal('hide');
        processQueue(data);
	})
</script>

<script>
	function processQueue(data)
	{
		$.ajax({
			url: 'admin/ajax.php?action=save_queue',
			method: 'POST',
			data: data,
			error: function(err) {
				console.log(err);
				alert_toast("An error occurred", 'danger');
				end_load();
			},
			success: function(resp) {
				if (resp > 0) {
					end_load();
					alert_toast("Queue Registered Successfully", 'success');
					PrintDiv(resp);
				}
			}
		});
	}
</script>

<script>
	function PrintDiv(res) {
		// Make AJAX request to get the modal content
		$.ajax({
			url: 'queue_print.php',
			method: 'POST',
			data: {
				id: res
			},
			success: function(response) {
				document.getElementById("printDiv").innerHTML = response;
				$('#printDiv').printThis({
					importCSS: true,
					loadCSS: "",
					removeInline: true,
				});
			},
			error: function(error) {
				console.log(error);
			}
		});

	}
</script>