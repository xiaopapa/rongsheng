<html>
<head>
    <title>Upload Form</title>
</head>
<body>

<?php echo $error;?>

<?php echo form_open_multipart('http://baymax-test.oa.com/upload/img_upload');?>

<input type="file" name="Filedata" size="20" />

<br /><br />

<input type="submit" value="upload" />
<input type="hidden" name="nameformat" value="md5|ym|8" />
<input type="hidden" name="samename" value="1" />

</form>

</body>
</html>