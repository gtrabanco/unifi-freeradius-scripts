# unifi-freeradius-scripts
Unifi Scripts to use with freeradius to have traffic limitations for the users

# Reasons
Unifi AP does not implement many RADIUS AVPs and I needed to restrict traffic for users in my network. Because Unifi Controller implements a quite well API I've done the implementation through its API with PHP scripting.

# Stuff this do...
- This can check if there are devices without unauthorized access in the network and force reconnect them
- We also can give a maximum data/day or whatever period you want between hourly, daily, weekly, monthly or yearly and if the user overpass that limit put him in a slower group.
- You can adapt this script to after that period disconnect the user

# TODO
There are some documented errors that could be solved better than I did but I had no time and this is not my work right now. I do not also has a system to do this and solve the problems. You can offer me a job if you want me to do this stuff :)

# Documentation

More documentation will be here at anytime or not... sorry!

26th April 2020 - Started to write the Wiki to document all project it will be all available slowly. Just check it frecuently.
