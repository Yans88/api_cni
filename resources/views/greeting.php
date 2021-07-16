<html>
<style>
html,body{
	background-color:#111;
	color:#fff;
}
.container {
  height: 100vh;
  width: 100vw;
  font-family: Helvetica;
}

.loader {
  height: 20px;
  width: 250px;
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  margin: auto;
}
p{
  font-weight:400;  
  top: 0;
  bottom: 0;  
  right: 0;
  margin: auto;
}
.loader--dot {
  animation-name: loader;
  animation-timing-function: ease-in-out;
  animation-duration: 3s;
  animation-iteration-count: infinite;
  height: 20px;
  width: 20px;
  border-radius: 100%;
  background-color: black;
  position: absolute;
  border: 2px solid white;
}
.loader--dot:first-child {
  background-color: #8cc759;
  animation-delay: 0.5s;
}
.loader--dot:nth-child(2) {
  background-color: #8c6daf;
  animation-delay: 0.4s;
}
.loader--dot:nth-child(3) {
  background-color: #ef5d74;
  animation-delay: 0.3s;
}
.loader--dot:nth-child(4) {
  background-color: #f9a74b;
  animation-delay: 0.2s;
}
.loader--dot:nth-child(5) {
  background-color: #60beeb;
  animation-delay: 0.1s;
}
.loader--dot:nth-child(6) {
  background-color: #fbef5a;
  animation-delay: 0s;
}
.loader--text {
  position: absolute;
  top: 200%;
  left: 0;
  right: 0;
  width: 4rem;
  margin: auto;
}
.loader--text:after {
  content: "Loading";
  font-weight: bold;
  animation-name: loading-text;
  animation-duration: 3s;
  animation-iteration-count: infinite;
}

@keyframes loader {
  15% {
    transform: translateX(0);
  }
  45% {
    transform: translateX(230px);
  }
  65% {
    transform: translateX(230px);
  }
  95% {
    transform: translateX(0);
  }
}
@keyframes loading-text {
  0% {
    content: "Loading";
  }
  25% {
    content: "Loading.";
  }
  50% {
    content: "Loading..";
  }
  75% {
    content: "Loading...";
  }
}

</style>

    <body>
	
        <p><b>Hello, <?php echo $nama_member; ?></b><br/>
		<?php 

// $words = $ttl_price . '' . $MALLID . 'NRd509eQng1F' . '' . $id_transaksi;

if($id_transaksi > 0){
?>
		Tunggu sebentar, anda akan diarahkan ke halaman pembayaran ...</p>
		<div class='container'>
  <div class='loader'>
    <div class='loader--dot'></div>
    <div class='loader--dot'></div>
    <div class='loader--dot'></div>
    <div class='loader--dot'></div>
    <div class='loader--dot'></div>
    <div class='loader--dot'></div>
    <div class='loader--text'></div>
	
  </div>
  
</div>

        
    <div style="clear:both; display:none;"></div>
<h1>OneCheckout - Payment Page CC - Tester</h1>
<form action="https://staging.doku.com/Suite/Receive" method="post" >
<table width="600" border="0" cellspacing="1" cellpadding="5">
  <tr>
    <td width="100" class="field_label">BASKET</td>
    <td width="500" class="field_input"><input name="BASKET" value="<?php echo $basket;?>" type="text" id="BASKET" size="100" /></td>
  </tr>
  <tr>
    <td width="100" class="field_label">MALLID</td>
    <td width="500" class="field_input"><input name="MALLID" value="<?php echo $mall_id;?>" type="text" id="MALLID" size="12" /> --> Mall ID CNI</td>
  </tr>
  <tr>
    <td width="100" class="field_label">CHAINMERCHANT</td>
    <td width="500" class="field_input"><input name="CHAINMERCHANT" type="text" id="CHAINMERCHANT" value="NA" size="12" /></td>
  </tr>
  <tr>
    <td class="field_label">CURRENCY</td>
    <td class="field_input"><input name="CURRENCY" type="text" id="CURRENCY" value="360" size="3" maxlength="3" /></td>
  </tr>
  <tr>
    <td class="field_label">PURCHASECURRENCY</td>
    <td class="field_input"><input name="PURCHASECURRENCY" type="text" id="PURCHASECURRENCY" value="360" size="3" maxlength="3" /></td>
  </tr>
  <tr>
    <td class="field_label">AMOUNT</td>
    <td class="field_input"><input name="AMOUNT" type="text" id="AMOUNT" value="<?php echo $ttl_price . '.00';?>" size="12" /></td>
  </tr>
  <tr>
    <td class="field_label">PURCHASEAMOUNT</td>
    <td class="field_input"><input name="PURCHASEAMOUNT" type="text" id="PURCHASEAMOUNT" value="<?php echo $ttl_price . '.00';?>" size="12" /></td>
  </tr>
  <tr>
    <td class="field_label">TRANSIDMERCHANT</td>
    <td class="field_input"><input name="TRANSIDMERCHANT" value="<?php echo $id_transaksi;?>" type="text" id="TRANSIDMERCHANT" size="16" /></td>
  </tr>
  
  <tr>
    <td class="field_label">WORDS ORI</td>
    <td class="field_input"><input type="text" id="WORDS" value="<?php echo $words_ori;?>" name="WORDS_ORI"  size="60" /></td>
  </tr>
  
  <tr>
    <td class="field_label">WORDS</td>
    <td class="field_input"><input type="text" id="WORDS" value="<?php echo $words;?>" name="WORDS"  size="60" /></td>
  </tr>
  <tr>
    <td class="field_label">REQUESTDATETIME</td>
    <td class="field_input"><input name="REQUESTDATETIME" value="<?php echo $tgl;?>" type="text" id="REQUESTDATETIME" size="14" maxlength="14" />
      (YYYYMMDDHHMMSS)</td>
  </tr>

  <tr>
    <td class="field_label">SESSIONID</td>
    <td class="field_input"><input type="text" id="SESSIONID" value="<?php echo $session_id;?>" name="SESSIONID" /></td>
  </tr>
  <tr>
    <td class="field_label">PAYMENTCHANNEL</td>
    <td class="field_input"><input type="text" id="PAYMENTCHANNEL" name="PAYMENTCHANNEL" value="15" /> </td>
  </tr>
  <tr>
    <td class="field_label">NAME</td>
    <td class="field_input"><input name="NAME" type="text" id="NAME" value="<?php echo $nama_member;?>" size="30" maxlength="50" /></td>
  </tr>
  <tr>
    <td width="100" class="field_label">EMAIL</td>
    <td width="500" class="field_input"><input name="EMAIL" type="text" id="EMAIL" value="<?php echo $email;?>"  size="12" /></td>
  </tr>

  <tr>
  	<td class="field_input" colspan="2">&nbsp;</td>
  </tr>
  
</table><br />
<input name="submit" type="submit" class="bt_submit" id="submit_bro" value="SUBMIT" />
</form>
<?php }else{
	echo 'Transaksi anda sudah expired</p>';
}	?>
    </body>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script language="javascript" type="text/javascript"> 
  $(document).ready(function (e) {
    setTimeout($('#submit_bro').click(), 1000);
  });

</script
</html>