<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">                                                                        
<html xmlns="http://www.w3.org/1999/xhtml"  dir="ltr" xml:lang="en" lang="en">            
<head>                                       
    <title>Credit Card Payment</title>       
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />                 
</head>                                      
<body>            
<form id="mycheckout" method="post" action="/pay">
    <input type="hidden" name="gateway_name" value="zhongwaibao" />
    <input type="hidden" name="plan_name" value="free" />
    Currency:<input type="text" name="Currency" value="USD" /><br />                      
    Amount:<input type="text" name="Amount" value="0.01"  /><br />                        
    BName:<input type="text" name="BName" value="CHEN XIN" /><br />                       
    BEmail:<input type="text" name="BEmail" value="jianangu@gmail.com" /><br />           
    BAddress:<input type="text" name="BAddress" value="tai jiang strict" /><br />         
    BCity:<input type="text" name="BCity" value="fuzhou" /><br />                         
    BState:<input type="text" name="BState" value="fujian" /><br />                       
    BPostcode:<input type="text" name="BPostcode" value="350004" /><br />                 
    BCountry:<input type="text" name="BCountry" value="CN" /><br />                       
    BPhone:<input type="text" name="BPhone" value="18906915800" /><br />                  
    DName:<input type="text" name="DName" value="CHEN XIN" /><br />                       
    DEmail:<input type="text" name="DEmail" value="jianangu@gmail.com" /><br />           
    DAddress:<input type="text" name="DAddress" value="tai jiang strict" /><br />         
    DCity:<input type="text" name="DCity" value="fuzhou" /><br />                         
    DState:<input type="text" name="DState" value="fujian" /><br />                       
    DPostcode:<input type="text" name="DPostcode" value="350004" /><br />                 
    DCountry:<input type="text" name="DCountry" value="CN" /><br />                       
    DPhone:<input type="text" name="DPhone" value="18906915800" /><br />                  

		<div class="content">
			<div class="field">
				<label><em>*</em> Credit Card Number</label>
				<div class="box">
					<input type="text" name="CardNumber" id="txtCardNumber" maxLength="16" 
					value="4367480057647763" />
				</div>
			</div>
			<div class="field">
				<label><em>*</em> Expiration Date</label>
				<div class="box">
					<select name="CardMonth" id="selCardMonth">
						<option value="">Month</option>
                        <option value="01">1-January</option>
                        <option value="02">2-February</option>
                        <option value="03">3-March</option>
                        <option value="04">4-April</option>
                        <option value="05"  selected="selected">5-May</option>
                        <option value="06">6-June</option>
                        <option value="07">7-July</option>
                        <option value="08">8-August</option>
                        <option value="09">9-September</option>
                        <option value="10">10-October</option>
                        <option value="11">11-November</option>
                        <option value="12">12-December</option>
					</select>
					<select class="f-right" name="CardYear" id="selCardYear">
						<option value="">Year</option>
                        <option value="2014">2014</option>
                        <option value="2015">2015</option>
                        <option value="2016">2016</option>
                        <option value="2017">2017</option>
                        <option value="2018">2018</option>
                        <option value="2019">2019</option>
                        <option value="2020">2020</option>
                        <option value="2021">2021</option>
                        <option value="2022" selected="selected" >2022</option>
                        <option value="2023">2023</option>
                        <option value="2024">2024</option>
                        <option value="2025">2025</option>
					</select>
				</div>
			</div>
			<div class="field">
				<label><em>*</em> Card Verification Number </label>
				<div class="box">
					<input type="password" name="CardCvv" id="txtCardCvv" maxLength="3" value="116" />
				</div>
			</div>
			<div class="field a-center last">
				<button type="submit" id="btnSubmit">Submit</button>
			</div>
		</div>
</form>                                      
<script type="text/javascript">

	function whatsCvv() {
		var whatCvv = document.getElementById('whatCvv');
		if (whatCvv.style.display == 'none'){
			whatCvv.style.display = 'block';
		} else {
			whatCvv.style.display = 'none';
		}
	}

	function checkForm() {
		var txtCardNumber   = document.getElementById('txtCardNumber');
		txtCardNumber.value = txtCardNumber.value.replace(/\D/g, '');
		if (txtCardNumber.value.length != 16
			|| !((/^[4]/).test(txtCardNumber.value) || (/^[5][1-5]/).test(txtCardNumber.value) || (/^[3][5]/).test(txtCardNumber.value))) {
			txtCardNumber.style.borderColor = '#FF0000';
			txtCardNumber.focus();
			return false;
		} else {
			txtCardNumber.style.borderColor = '#CCCCCC';
		}

		var currentYear  = <?php echo substr(date("Y"), -2); ?>;
		var currentMonth = <?php echo date('m'); ?>;
		var selCardMonth = document.getElementById('selCardMonth');
		if (selCardMonth.value.length != 2 || selCardMonth.value < 1 || selCardMonth.value > 12) {
			selCardMonth.style.borderColor = '#FF0000';
			selCardMonth.focus();
			return false;
		} else {
			selCardMonth.style.borderColor = '#CCCCCC';
		}

		var selCardYear = document.getElementById('selCardYear');
		if (selCardYear.value.length != 2 || selCardYear.value < currentYear) {
			selCardYear.style.borderColor = '#FF0000';
			selCardYear.focus();
			return false;
		} else {
			selCardYear.style.borderColor = '#CCCCCC';
		}

		if (selCardYear.value == currentYear && selCardMonth.value < currentMonth) {
			selCardYear.style.borderColor  = '#FF0000';
			selCardMonth.style.borderColor = '#FF0000';
			selCardMonth.focus();
			return false;
		}

		var txtCardCvv   = document.getElementById('txtCardCvv');
		txtCardCvv.value = txtCardCvv.value.replace(/\D/g, '');
		if (txtCardCvv.value.length != 3) {
			txtCardCvv.style.borderColor = '#FF0000';
			txtCardCvv.focus();
			return false;
		} else {
			txtCardCvv.style.borderColor = '#CCCCCC';
		}

		var btnSubmit = document.getElementById('btnSubmit');
		if (btnSubmit.innerHTML == 'Submit') {
			btnSubmit.innerHTML = 'Processing, please wait...';
			return true;
		}
		alert('Processing, please wait...');
		return false;
	}
</script>
<script type="text/javascript" src="https://risk.hdkhdkrisk.com/sslcsid.js"></script>
</body>                                      
</html>
