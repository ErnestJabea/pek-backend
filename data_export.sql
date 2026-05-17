SET FOREIGN_KEY_CHECKS = 0;

-- Data for table users
INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `role`, `city`, `country`, `employer`) VALUES ('1', 'Admin', 'PEK', 'admin@pek.com', NULL, NULL, '$2y$10$m7dEkK9aP8YotMYpg1SlbeDyDy.MRpEuY1RiINWS56lCop.nAhhdK', NULL, '2026-05-14 06:38:11', '2026-05-14 07:17:24', 'admin', NULL, NULL, NULL);
INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `role`, `city`, `country`, `employer`) VALUES ('5', 'Lala', 'Syllas', 'ernestjabea@gmail.com', '+2376 90 58 64 96', '2026-05-14 13:07:31', '$2y$10$0vmkebcevAm.a1/E7r0IgutNLu5CUd/t5lQrGUvApkNlynifcW.ca', NULL, '2026-05-14 13:06:42', '2026-05-14 13:52:35', 'client', 'Douala', 'Cameroun', 'KAM');

-- Data for table personal_access_tokens
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES ('1', 'App\Models\User', '4', 'auth_token', '956818ddda4da557e0a7380b7176c083dac25d17d579f454ec9d681950b83c86', '["*"]', '2026-05-14 12:32:04', NULL, '2026-05-14 12:30:27', '2026-05-14 12:32:04');
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES ('2', 'App\Models\User', '5', 'auth_token', '71ecca6964f772f74e1b258fc43153a5fa3766b9bd9e8341ba234b9605d291f4', '["*"]', '2026-05-14 20:10:31', NULL, '2026-05-14 13:07:31', '2026-05-14 20:10:31');

-- Data for table products
INSERT INTO `products` (`id`, `libelle`, `description`, `vl`, `seuil_minimum`, `created_at`, `updated_at`) VALUES ('1', 'FCP Kori Sérénité', 'Idéal pour une épargne de précaution avec un risque maîtrisé. Investissement prudent et stable.', '11364.41', '50000', '2026-05-13 16:37:18', '2026-05-14 13:14:47');
INSERT INTO `products` (`id`, `libelle`, `description`, `vl`, `seuil_minimum`, `created_at`, `updated_at`) VALUES ('2', 'FCP Croissance', 'Optimisez vos rendements sur le long terme via une allocation diversifiée. Performance dynamique.', '13637.292', '100000', '2026-05-13 16:37:18', '2026-05-14 07:08:07');
INSERT INTO `products` (`id`, `libelle`, `description`, `vl`, `seuil_minimum`, `created_at`, `updated_at`) VALUES ('3', 'FCP Horizon 2030', 'Un fonds dynamique pour préparer vos projets d''avenir avec un horizon de placement long.', '17046.615', '250000', '2026-05-13 16:37:18', '2026-05-14 07:08:07');

-- Data for table subscriptions
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('1', '5', '1', '21', '11364.41', '238652.61', 'stripe', 'En attente', 'FCP-59FF6CEB', '2026-05-14 15:14:20', '2026-05-14 15:14:20');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('2', '5', '1', '16', '11364.41', '181830.56', 'orange_money', 'En attente', 'FCP-1EE2F77E', '2026-05-14 15:26:17', '2026-05-14 15:26:17');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('3', '5', '1', '7.7', '11364.41', '87505.957', 'orange_money', 'En attente', 'FCP-E6C92DFA', '2026-05-14 16:17:28', '2026-05-14 16:17:28');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('4', '5', '1', '6.9', '11364.41', '78414.429', 'stripe', 'En attente', 'FCP-ABD5E87B', '2026-05-14 16:17:53', '2026-05-14 16:17:53');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('5', '5', '1', '7.6', '11364.41', '86369.516', 'stripe', 'En attente', 'FCP-4F50016D', '2026-05-14 16:23:41', '2026-05-14 16:23:41');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('6', '5', '1', '6.8', '11364.41', '77277.988', 'bank_card', 'En attente', 'FCP-625A5951', '2026-05-14 16:25:51', '2026-05-14 16:25:51');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('7', '5', '1', '6.4', '11364.41', '72732.224', 'bank_card', 'En attente', 'FCP-7122B276', '2026-05-14 16:30:42', '2026-05-14 16:30:42');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('8', '5', '1', '6.4', '11364.41', '72732.224', 'bank_card', 'En attente', 'FCP-88544748', '2026-05-14 16:32:06', '2026-05-14 16:32:06');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('9', '5', '1', '8.2', '11364.41', '93188.162', 'bank_card', 'En attente', 'FCP-0E94CEE9', '2026-05-14 16:38:36', '2026-05-14 16:38:36');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('10', '5', '3', '28.4', '17046.615', '484123.866', 'stripe', 'En attente', 'FCP-7415038C', '2026-05-14 16:48:04', '2026-05-14 16:48:04');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('11', '5', '1', '7.6', '11364.41', '86369.516', 'orange_money', 'En attente', 'FCP-207271D5', '2026-05-14 17:04:46', '2026-05-14 17:04:46');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('12', '5', '1', '10.7', '11364.41', '121599.187', 'orange_money', 'En attente', 'FCP-2D7D3D98', '2026-05-14 18:07:38', '2026-05-14 18:07:38');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('13', '5', '1', '8.7', '11364.41', '98870.367', 'orange_money', 'En attente', 'FCP-0D6BF469', '2026-05-14 18:11:21', '2026-05-14 18:11:21');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('14', '5', '2', '10', '13637.292', '136372.92', 'orange_money', 'En attente', 'FCP-2807BE99', '2026-05-14 18:18:00', '2026-05-14 18:18:00');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('15', '5', '2', '10', '13637.292', '136372.92', 'orange_money', 'En attente', 'FCP-58A0DBEB', '2026-05-14 18:19:32', '2026-05-14 18:19:32');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('16', '5', '2', '10', '13637.292', '136372.92', 'orange_money', 'En attente', 'FCP-4D6A1CAA', '2026-05-14 18:20:56', '2026-05-14 18:20:56');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('17', '5', '2', '10', '13637.292', '136372.92', 'orange_money', 'En attente', 'FCP-6B0230E9', '2026-05-14 18:22:12', '2026-05-14 18:22:12');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('18', '5', '2', '15.5', '13637.292', '211378.026', 'orange_money', 'En attente', 'FCP-D646E2CC', '2026-05-14 18:24:18', '2026-05-14 18:24:18');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('19', '5', '2', '15.5', '13637.292', '211378.026', 'orange_money', 'En attente', 'FCP-BCCAA836', '2026-05-14 18:39:42', '2026-05-14 18:39:42');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('20', '5', '2', '15.5', '13637.292', '211378.026', 'orange_money', 'En attente', 'FCP-7FD34B60', '2026-05-14 18:44:38', '2026-05-14 18:44:38');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('21', '5', '2', '10.5', '13637.292', '143191.566', 'orange_money', 'En attente', 'FCP-87C982F2', '2026-05-14 18:45:19', '2026-05-14 18:45:19');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('22', '5', '2', '10.5', '13637.292', '143191.566', 'orange_money', 'En attente', 'FCP-B9744548', '2026-05-14 18:45:35', '2026-05-14 18:45:35');
INSERT INTO `subscriptions` (`id`, `user_id`, `product_id`, `nb_parts`, `prix_unitaire`, `montant_total`, `moyen_paiement`, `statut`, `reference_transaction`, `created_at`, `updated_at`) VALUES ('23', '5', '2', '10.5', '13637.292', '143191.566', 'orange_money', 'En attente', 'FCP-B0646838', '2026-05-14 18:47:53', '2026-05-14 18:47:53');

-- Data for table otp_codes
INSERT INTO `otp_codes` (`id`, `email`, `code`, `expires_at`, `created_at`, `updated_at`) VALUES ('1', 'ernestjabea@gmail.com', '619969', '2026-05-14 12:21:56', '2026-05-14 12:11:56', '2026-05-14 12:11:56');
INSERT INTO `otp_codes` (`id`, `email`, `code`, `expires_at`, `created_at`, `updated_at`) VALUES ('2', 'ernestjabs@gmail.com', '423633', '2026-05-14 12:30:28', '2026-05-14 12:20:28', '2026-05-14 12:20:28');

-- Data for table product_vls
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('1', '1', '10965.15', '2026-01-02 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('2', '1', '10968.73', '2026-01-09 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('3', '1', '10973.01', '2026-01-16 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('4', '1', '10976.22', '2026-01-23 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('5', '1', '10979.81', '2026-01-30 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('6', '1', '10986.12', '2026-02-06 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('7', '1', '10987.61', '2026-02-13 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('8', '1', '11042.2', '2026-02-20 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('9', '1', '11290.68', '2026-02-27 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('10', '1', '11293.51', '2026-03-06 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('11', '1', '11300.05', '2026-03-13 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('12', '1', '11303.62', '2026-03-20 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('13', '1', '11308.58', '2026-03-27 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('14', '1', '11330.05', '2026-04-03 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('15', '1', '11332.83', '2026-04-10 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('16', '1', '11352.88', '2026-04-17 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('17', '1', '11357.49', '2026-04-24 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('18', '1', '11364.41', '2026-05-01 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('19', '2', '13158.18', '2026-01-02 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('20', '2', '13162.476', '2026-01-09 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('21', '2', '13167.612', '2026-01-16 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('22', '2', '13171.464', '2026-01-23 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('23', '2', '13175.772', '2026-01-30 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('24', '2', '13183.344', '2026-02-06 00:00:00', '2026-05-14 07:08:06', '2026-05-14 07:08:06');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('25', '2', '13185.132', '2026-02-13 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('26', '2', '13250.64', '2026-02-20 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('27', '2', '13548.816', '2026-02-27 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('28', '2', '13552.212', '2026-03-06 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('29', '2', '13560.06', '2026-03-13 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('30', '2', '13564.344', '2026-03-20 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('31', '2', '13570.296', '2026-03-27 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('32', '2', '13596.06', '2026-04-03 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('33', '2', '13599.396', '2026-04-10 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('34', '2', '13623.456', '2026-04-17 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('35', '2', '13628.988', '2026-04-24 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('36', '2', '13637.292', '2026-05-01 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('37', '3', '16447.725', '2026-01-02 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('38', '3', '16453.095', '2026-01-09 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('39', '3', '16459.515', '2026-01-16 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('40', '3', '16464.33', '2026-01-23 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('41', '3', '16469.715', '2026-01-30 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('42', '3', '16479.18', '2026-02-06 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('43', '3', '16481.415', '2026-02-13 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('44', '3', '16563.3', '2026-02-20 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('45', '3', '16936.02', '2026-02-27 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('46', '3', '16940.265', '2026-03-06 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('47', '3', '16950.075', '2026-03-13 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('48', '3', '16955.43', '2026-03-20 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('49', '3', '16962.87', '2026-03-27 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('50', '3', '16995.075', '2026-04-03 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('51', '3', '16999.245', '2026-04-10 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('52', '3', '17029.32', '2026-04-17 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('53', '3', '17036.235', '2026-04-24 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');
INSERT INTO `product_vls` (`id`, `product_id`, `vl`, `date_vl`, `created_at`, `updated_at`) VALUES ('54', '3', '17046.615', '2026-05-01 00:00:00', '2026-05-14 07:08:07', '2026-05-14 07:08:07');

-- Data for table permissions
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('1', 'view_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('2', 'view_any_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('3', 'create_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('4', 'update_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('5', 'restore_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('6', 'restore_any_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('7', 'replicate_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('8', 'reorder_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('9', 'delete_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('10', 'delete_any_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('11', 'force_delete_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('12', 'force_delete_any_client', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('13', 'view_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('14', 'view_any_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('15', 'create_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('16', 'update_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('17', 'restore_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('18', 'restore_any_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('19', 'replicate_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('20', 'reorder_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('21', 'delete_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('22', 'delete_any_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('23', 'force_delete_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('24', 'force_delete_any_product', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('25', 'view_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('26', 'view_any_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('27', 'create_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('28', 'update_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('29', 'restore_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('30', 'restore_any_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('31', 'replicate_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('32', 'reorder_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('33', 'delete_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('34', 'delete_any_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('35', 'force_delete_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('36', 'force_delete_any_subscription', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('37', 'view_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('38', 'view_any_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('39', 'create_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('40', 'update_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('41', 'restore_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('42', 'restore_any_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('43', 'replicate_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('44', 'reorder_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('45', 'delete_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('46', 'delete_any_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('47', 'force_delete_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('48', 'force_delete_any_user', 'web', '2026-05-14 07:41:53', '2026-05-14 07:41:53');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('49', 'view_role', 'web', '2026-05-14 07:46:11', '2026-05-14 07:46:11');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('50', 'view_any_role', 'web', '2026-05-14 07:46:11', '2026-05-14 07:46:11');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('51', 'create_role', 'web', '2026-05-14 07:46:11', '2026-05-14 07:46:11');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('52', 'update_role', 'web', '2026-05-14 07:46:11', '2026-05-14 07:46:11');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('53', 'delete_role', 'web', '2026-05-14 07:46:11', '2026-05-14 07:46:11');
INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('54', 'delete_any_role', 'web', '2026-05-14 07:46:11', '2026-05-14 07:46:11');

-- Data for table roles
INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ('1', 'super_admin', 'web', '2026-05-14 07:41:52', '2026-05-14 07:41:52');

-- Data for table model_has_roles
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES ('1', 'App\Models\User', '1');

-- Data for table role_has_permissions
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('1', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('2', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('3', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('4', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('5', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('6', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('7', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('8', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('9', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('10', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('11', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('12', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('13', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('14', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('15', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('16', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('17', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('18', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('19', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('20', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('21', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('22', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('23', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('24', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('25', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('26', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('27', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('28', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('29', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('30', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('31', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('32', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('33', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('34', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('35', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('36', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('37', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('38', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('39', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('40', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('41', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('42', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('43', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('44', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('45', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('46', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('47', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('48', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('49', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('50', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('51', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('52', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('53', '1');
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES ('54', '1');

-- Data for table currencies
INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `exchange_rate`, `is_base`, `created_at`, `updated_at`) VALUES ('1', 'XAF', 'Franc CFA', 'FCFA', '1', '1', '2026-05-14 07:56:53', '2026-05-14 07:56:53');
INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `exchange_rate`, `is_base`, `created_at`, `updated_at`) VALUES ('2', 'EUR', 'Euro', '€', '655.957', '0', '2026-05-14 07:56:53', '2026-05-14 07:56:53');
INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `exchange_rate`, `is_base`, `created_at`, `updated_at`) VALUES ('3', 'USD', 'Dollar US', '$', '610', '0', '2026-05-14 07:56:53', '2026-05-14 07:56:53');

-- Data for table notifications
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('1', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Kori Sérénité (FCP-E6C92DFA) est en attente de validation.', 'subscription', NULL, '2026-05-14 16:17:28', '2026-05-14 16:17:28');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('2', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Kori Sérénité (FCP-ABD5E87B) est en attente de validation.', 'subscription', NULL, '2026-05-14 16:17:55', '2026-05-14 16:17:55');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('3', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Kori Sérénité (FCP-4F50016D) est en attente de validation.', 'subscription', NULL, '2026-05-14 16:23:43', '2026-05-14 16:23:43');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('4', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Kori Sérénité (FCP-625A5951) est en attente de validation.', 'subscription', NULL, '2026-05-14 16:25:53', '2026-05-14 16:25:53');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('5', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Kori Sérénité (FCP-7122B276) est en attente de validation.', 'subscription', NULL, '2026-05-14 16:30:44', '2026-05-14 16:30:44');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('6', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Kori Sérénité (FCP-88544748) est en attente de validation.', 'subscription', NULL, '2026-05-14 16:32:08', '2026-05-14 16:32:08');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('7', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Kori Sérénité (FCP-0E94CEE9) est en attente de validation.', 'subscription', NULL, '2026-05-14 16:38:37', '2026-05-14 16:38:37');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('8', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Horizon 2030 (FCP-7415038C) est en attente de validation.', 'subscription', NULL, '2026-05-14 16:48:05', '2026-05-14 16:48:05');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('9', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Kori Sérénité (FCP-207271D5) est en attente de validation.', 'subscription', NULL, '2026-05-14 17:04:46', '2026-05-14 17:04:46');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('10', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Kori Sérénité (FCP-2D7D3D98) est en attente.', 'subscription', NULL, '2026-05-14 18:07:38', '2026-05-14 18:07:38');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_at`, `updated_at`) VALUES ('11', '5', 'Souscription enregistrée', 'Votre demande de souscription pour FCP Kori Sérénité (FCP-0D6BF469) est en attente.', 'subscription', NULL, '2026-05-14 18:11:21', '2026-05-14 18:11:21');

-- Data for table bank_details
INSERT INTO `bank_details` (`id`, `bank_name`, `iban`, `rib`, `swift`, `is_active`, `created_at`, `updated_at`) VALUES ('1', 'AFRILAND FIRST BANK', 'AFB 66 0400 1200 0000 1234 5678', '12345678901', 'AFBJCC', '1', '2026-05-14 17:37:49', '2026-05-14 17:39:47');

SET FOREIGN_KEY_CHECKS = 1;
