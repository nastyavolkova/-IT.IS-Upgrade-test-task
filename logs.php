<?php

ini_set('max_execution_time', 20000);

// определение типа действий на сайте
function action_definition($line){

    $number_of_elements = array_key_exists('4', $line);

    if ($number_of_elements){

        if(substr($line[4], 0, 5) === "cart?"){
            return "adding";
        }
        elseif(substr($line[4], 0, 7) === "success"){
            return "buy";
        }
    
        elseif((substr($line[4], 0, 4) !== "pay?") and (array_key_exists('5', $line))){
            return "product";
        }
        elseif(substr($line[4], 0, 4) !== "pay?"){
            return "category";
        }
    }
    else {
        return "main page";
    }

}

function record($line, $index, $column, $table, $conn){

    if($table !== 'carts'){
        $meaning = $line[$index];
    }  
    else {
        $meaning = substr($line[$index], 8, 4);
    } 

    $query = "SELECT $column FROM $table WHERE ($column = '$meaning')";
    $originality = mysqli_num_rows(mysqli_query($conn, $query));

  if ($originality === 0){

        $query ="INSERT INTO $table ($column) VALUES ('$meaning')";
        $result = mysqli_query($conn, $query);
        return mysqli_insert_id($conn);
    }
}

function time_record($line, $table, $column, $conn){

    $time = $line[0];

    $date = date('Y-m-d H:i:s', strtotime($time));

    $query ="INSERT INTO $table ($column) VALUES ('$date')";

    $result = mysqli_query($conn, $query);

    return mysqli_insert_id($conn);
    
}

$logs_txt = dirname(__FILE__) . '/logs.txt';

$file = fopen($logs_txt,"r");

// соединение с БД
$servername = "127.0.0.1";
$database = "test";
$username = "root";
$password = "";

$conn = mysqli_connect($servername, $username, $password, $database);
 
while (feof($file) != True){
    $string = fgets($file);
    $string = stristr($string,'20');    //возвращает строку, начиная с '20'

    $replace = array("","", "|", "|", "|", "|", "|");   //на что заменяем
    $search = array(" ", "INFO:", "[", "]", "https://", "/", "&");   //что заменяем
    $string = str_replace($search, $replace, $string);

    $string = trim($string, "|\r\n");     // удаляет символ | из конца строки

    $empty_line = empty($string);

    if (!$empty_line){
    
        $line = explode("|", $string);
  
        $type = action_definition($line);     // тип действия на сайте
    
        //запись IP 
        $index = 2; 
        $column = 'IP'; 
        $table = 'users';

        $current_user_id = record($line, $index, $column, $table, $conn);
    
        // запись времени просмотра/ добавления/ покупки
        if($type === 'main page' || $type === 'product' || $type === 'category'){
            $column = 'time_view';
            $table = 'views';
        }
        elseif($type === 'adding'){
            $column = 'adding_time';
            $table = 'addings';
        }
        elseif($type === 'buy'){
            $column = 'purchase_time';
            $table = 'purchases'; 
        }

        if($type !== null){

            $current_id_view = time_record($line, $table, $column, $conn);
        }

        if($type === 'category' or $type === 'product'){

            //запись category
            $index = 4; 
            $column = 'category_name'; 
            $table = 'prod_categories';

            $current_id_category = record($line, $index, $column, $table, $conn);

            // запись категории в просмотры

            $category = $line[4];
            $id_category_query = "SELECT id_prod_category FROM prod_categories WHERE (category_name = '$category')";
            $current_id_category =  mysqli_fetch_assoc(mysqli_query($conn, $id_category_query))['id_prod_category'];

            $query = "UPDATE views SET id_prod_category = '$current_id_category' where id_view = '$current_id_view'";
            $result = mysqli_query($conn, $query);

            if($type === 'product'){

                //запись product
                $index = 5;
                $column = 'product_name';
                $table = 'products';

                $current_id_product = record($line, $index, $column, $table, $conn);

                if($current_id_product !== null){
                    //запись категории в продукты
                    $category = $line[4];
                    $id_category_query = "SELECT id_prod_category FROM prod_categories WHERE (category_name = '$category')";
                    $current_id_category =  mysqli_fetch_assoc(mysqli_query($conn, $id_category_query))['id_prod_category'];

                    $query = "UPDATE products SET id_prod_category = '$current_id_category' where id_product = '$current_id_product'";
                    $result = mysqli_query($conn, $query);
                }

                // запись продукта в просмотры
                $product = $line[5];
                $id_product_query = "SELECT id_product FROM products WHERE (product_name = '$product')";
                $current_id_product = mysqli_fetch_assoc(mysqli_query($conn, $id_product_query))['id_product'];

                $query = "UPDATE views SET id_product = '$current_id_product' where id_view = '$current_id_view'";
                $result = mysqli_query($conn, $query);
            }
        }

        // запись id в просмотры
        if($type === 'main page' || $type === 'product' || $type === 'category'){
        
            $ip = $line[2];
            $id_user_query = "SELECT id_user FROM users WHERE (IP = '$ip')";
            $current_user_id =  mysqli_fetch_assoc(mysqli_query($conn, $id_user_query))['id_user'];
    
            $query = "UPDATE views SET id_user = '$current_user_id' where id_view = '$current_id_view'";
            $result = mysqli_query($conn, $query);
        }
        // запись в добавления
        if($type === 'adding'){
        
            // количество в добавления

            //вычисление количества
            $qt = strlen($line[5]) - 7;
            $amount = substr($line[5], -$qt);

            $query = "UPDATE addings SET amount = '$amount' where id_adding = '$current_id_view'";
            $result = mysqli_query($conn, $query);

            // запись в корзины
            $index = 6; 
            $column = 'cart_number'; 
            $table = 'carts';

            $current_id_cart = record($line, $index, $column, $table, $conn);

            // запись корзин в добавления
            $cart = substr($line[6], 8, 4);
            $id_cart_query = "SELECT id_cart FROM carts WHERE (cart_number = '$cart')";
            $current_id_cart = mysqli_fetch_assoc(mysqli_query($conn, $id_cart_query))['id_cart'];

            $query = "UPDATE addings SET id_cart = '$current_id_cart' where id_adding = '$current_id_view'";
            $result = mysqli_query($conn, $query);

            // поиск последнего просмотра пользователем
            $ip = $line[2];
            $id_user_query = "SELECT id_user FROM users WHERE (IP = '$ip')";
            $current_user_id =  mysqli_fetch_assoc(mysqli_query($conn, $id_user_query))['id_user'];

            // id просмотра 
            $query = "SELECT id_view FROM views WHERE time_view = (SELECT MAX(time_view) FROM views WHERE id_user = '$current_user_id')";
            $viewed_id = mysqli_fetch_assoc(mysqli_query($conn, $query))['id_view'];


            // запись id добавления в просмотры
            $query = " UPDATE views SET id_adding = '$current_id_view' WHERE id_view = '$viewed_id'";
            $result = mysqli_query($conn, $query);
        }

        // запись в покупки
        if($type === 'buy'){

            // запись корзин в покупки
            $cart = substr($line[4], 12, 4);
            $id_cart_query = "SELECT id_cart FROM carts WHERE (cart_number = '$cart')";
            $current_id_cart = mysqli_fetch_assoc(mysqli_query($conn, $id_cart_query))['id_cart'];

            //$query = "UPDATE purchases SET id_cart = '$current_id_cart' where id_purchase = '$current_id_view'";
            //$result = mysqli_query($conn, $query);


            // поиск id добавления 
            $query = "SELECT id_adding FROM addings WHERE (id_cart = '$current_id_cart')";
            
           // $id_adding = mysqli_fetch_array(mysqli_query($conn, $query));
            $result = mysqli_query($conn, $query);
            
            $rows = mysqli_num_rows($result); // количество полученных строк
            
            $id_adding = array();

            for ($i = 0 ; $i < $rows ; ++$i)
            {
                $id_adding[] = mysqli_fetch_array($result)['id_adding'];
               
            }

            // запись id покупки в добавления
            foreach ($id_adding as $row){
                $query = " UPDATE addings SET id_purchase = '$current_id_view' WHERE id_adding = '$row'";
                $result = mysqli_query($conn, $query);
            }
            
        }

    }


}

//закрывает подключение к БД
mysqli_close($conn);
// закрывает дескриптор файла
fclose($file);
?> 
