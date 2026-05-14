<?php
// Simple client type selector for adding projects
?>
<div class="card" style="margin-bottom:12px">
	<h4>Client Type</h4>
	<p>Select whether this project is for a new client or an existing client.</p>
	<button onclick="selectType('new')">New Client</button>
	<button onclick="selectType('existing')">Existing Client</button>
	<script>
	function selectType(t){
		if(t==='new'){
			// focus on add project form
			var el = document.querySelector('input[name="name"]');
			if(el) el.focus();
		} else {
			alert('Search existing clients in the Add Project form by typing client name.');
		}
	}
	</script>
</div>
