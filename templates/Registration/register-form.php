<?php
/** @var \Psr\Http\Message\ServerRequestInterface $request */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register</title>
</head>
<body>
<h1>Register</h1>
<form name="register_form" method="post" action="/register">
    <label>First Name <input type="text" name="brewerFirstName" required></label>
    <label>Last Name <input type="text" name="brewerLastName" required></label>
    <label>Email <input type="email" name="user_name" required></label>
    <label>Password <input type="password" name="password" required></label>
    <label>Confirm Password <input type="password" name="password-confirm" required></label>
    <fieldset>
        <legend>Security Question</legend>
        <label><input type="radio" name="userQuestion" value="Favorite hop?" checked> Favorite hop?</label>
        <label>Answer <input type="text" name="userQuestionAnswer" required></label>
    </fieldset>
    <label>Country
        <select name="brewerCountry" required>
            <option value="">Select...</option>
            <option value="United States">United States</option>
        </select>
    </label>
    <div id="address-fields">
        <label>Address <input type="text" name="brewerAddress" required></label>
        <label>City <input type="text" name="brewerCity" required></label>
        <label>State
            <select name="brewerStateUS">
                <option value="">Select...</option>
                <option value="TX">Texas [TX]</option>
            </select>
        </label>
        <label>Zip <input type="text" name="brewerZip" required></label>
    </div>
    <label>Phone <input type="tel" name="brewerPhone1" required></label>
    <button type="submit" name="submit">Register</button>
</form>
</body>
</html>
