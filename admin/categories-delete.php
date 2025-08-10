<?php

require_once('../config/function.php');

$paraRestultId = checkParamId('id');
if(is_numeric($paraRestultId)){

    $categoryId = validate($paraRestultId);
    
    $category = getById('categories',$categoryId);

    if($category['status'] == 200)
    {
        $response = delete('categories', $categoryId);
        if($response)
        {
            redirect('categories.php','Category Deleted Successfully');
        }
        else
        {
            redirect('categories.php','Something Went Wrong.');
        }
    }
    else
    {
        redirect('categories.php',$category['message']);
    }
}else{
    redirect('categories.php','Something Went Wrong.');
}

?>
