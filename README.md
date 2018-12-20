# KVObject
Full NoSQL for Mysql 

Imagine ... You are coding ... And you want to load a user data with a user id User_id

$myUser = new KVObject("user");
$myUser->loadFromArray(["userID"=>$User_id]);
echo $myUser->name;
$myUser->email = "yehushua.ben.david@gmail.com" ; // Hop it's in base

$myUser->email2 ; // equal "" if it's not in base 


Life is so Easy 



