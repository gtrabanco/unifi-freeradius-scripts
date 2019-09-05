# SQL to add the Custom Attributes to daloRadius

INSERT INTO `dictionary` (`Type`, `Attribute`, `Value`, `Format`, `Vendor`, `RecommendedOP`, `RecommendedTable`, `RecommendedHelper`, `RecommendedTooltip`) VALUES
('string', 'Customunifi-Speed-User-Group', NULL, NULL, 'Customunifi', ':=', 'reply', '', 'If you want to fix a usegroup for a user you should put it here. It would be defined in the unifi controller.'),
('string', 'Customunifi-Speed-Control-Reset-Period', NULL, NULL, 'Customunifi', ':=', 'reply', '', 'The period to reset the downspeed period. It should be: hourly, daily, weekly or yearly.'),
('string', 'Customunifi-Reduced-Speed-User-Group', NULL, NULL, 'Customunifi', ':=', 'reply', '', 'The name of the unifi controller usegroup for users that has been downspeeded'),
('integer', 'Customunifi-Max-Speed-Total-Data', NULL, NULL, 'Customunifi', ':=', 'check', 'volumebytes', 'Max download + upload octets before downspeed the user.'),
('integer', 'Customunifi-Max-Speed-Download-Data', NULL, NULL, 'Customunifi', ':=', 'check', 'volumebytes', 'Max download octets before downspeed the user.'),
('integer', 'Customunifi-Max-Speed-Upload-Data', NULL, NULL, 'Customunifi', ':=', 'check', 'volumebytes', 'Max octets to upload data before downspeed the user.');