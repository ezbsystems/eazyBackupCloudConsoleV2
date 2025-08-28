<div class="loader" id="whmcsloader">
	<img src="{$BASE_PATH_IMG}/loader.svg" alt="Loading...">
	<span>Loading...</span>
</div>


<iframe
	src="https://docs.eazybackup.com/"
	onload="document.getElementById('whmcsloader').style.display='none';"></iframe>

<script>
	$(function() {
		$('.sidebar').remove();
	});
</script>

<style>
.loader {
	position: fixed;
	top: 50%;
	width: 100%;
	height: 101%;
	background: #111827;
	z-index: 9;
	left: 60%;
	right: 0px;
	display: flex;
	justify-content: center;
	align-items: center;
	flex-direction: column;
	transform: translate(-50%, -50%);
}

div#whmcsloader span {
	color: #9ca3af;
	padding: 10px 0px 0px;
	font-size: 16px;
}

div#whmcsloader img {
	width: 100%;
	max-width: 45px;
}

iframe {
	border: none;
	outline: none;
	height: calc(100vh);
	width: 100%;
}

.container {
	width: 100%;
	padding: 0;
}

.container .navbar-collapse {
	padding: 0px 116px 0 117px;
}

section#main-body {
	padding: 0 0 0 0;
}

.main-content {
	margin: 0;
	padding: 0;
	width: 100%;
	height: 100vh;
}
</style>