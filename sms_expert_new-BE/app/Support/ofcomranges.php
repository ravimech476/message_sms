<?php
//**********************************************************************************************************************************
//	based on:: http://www.ofcom.org.uk/static/numbering/index.htm#pers
//  PORTED VERBATIM from OLD SYSTEM includes/library/ofcomranges.inc so smsg_log.ofcomnetid
//  matches the old system exactly. Global ofcom()/ofcomname() are wrapped by App\Support\Ofcom.
//**********************************************************************************************************************************
//**********************************************************************************************************************************

//**********************************************************************************************************************************
//Functions
//**********************************************************************************************************************************
function ofcom($msisdn) {

	$data = <<<DATA
7000,Vodafone Ltd
7002,Vodafone Ltd
7003,4D Interactive Ltd
7003,Call Sciences Limited
7003,Edge Telecom Limited
7003,Hospedia Limited
7003,Nexus Telecommunications plc
7003,Virtual Universe Ltd
7004,Daisy Communications Ltd
7005,Hospedia Limited
7005,Invomo Ltd
7005,Numbergroup Network Ltd
7005,Opal Telecommunications PLC
7005,TalkTalk Communications Limited
7005,TelXL Ltd
7006,Call Sciences Limited
7006,Danemere Street Creative
7006,Edge Telecom Limited
7006,Invomo Ltd
7006,Nexus Telecommunications plc
7006,Rexcom Tech Limited
7006,Sound Advertising Ltd
7006,Telecom2 Ltd
7006,TG Support Limited
7006,Tiscali UK Limited
7007,Premium O Limited
7008,Edge Telecom Limited
7008,Skymarket Limited
7008,Syntec UK Ltd
7008,TelXL Ltd
7008,Yim Siam Telecom
7009,Affiniti Integrated Solutions Ltd
7010,FleXtel Limited
7011,Invomo Ltd
7011,Magrathea Telecommunications Limited
7011,Skycom Ltd
7011,T.T.N.C. Limited
7011,Vital Phone Limited
7012,24 Seven Communications Ltd
7012,Edge Telecom Limited
7012,Invomo Ltd
7012,Nexus Telecommunications plc
7012,Oxygen8 Communications UK Limited
7012,Skymarket Limited
7012,Virtual Universe Ltd
7013,Edge Telecom Limited
7013,MDNX Enterprise Services Limited
7013,Oxygen8 Communications UK Limited
7013,Skymarket Limited
7014,Edge Telecom Limited
7014,FleXtel Limited
7014,Sky Telecom Limited
7014,YAC Ltd
7015,24 Seven Communications Ltd
7015,Business Broadcast Communications Ltd
7015,Cheers International Sales Limited
7015,Citrus Telecommunications Ltd
7015,Eclipse Tel Limited
7015,Edge Telecom Limited
7015,i-Net Communications Group Plc
7015,Jersey Telecom
7015,MDNX Enterprise Services Limited
7015,Orbis Telecom
7015,Skymarket Limited
7015,T 7 Solutions Limited
7015,YAC Ltd
7016,24 Seven Communications Ltd
7016,B4U Telecom Ltd
7016,Known Future Limited
7016,Skytel Ltd
7017,FleXtel Limited
7018,Daotec Ltd
7018,Rhodium Telecom Limited
7018,Skytel Ltd
7018,Syntec UK Ltd
7019,24 Seven Communications Ltd
7019,Skytel Ltd
7020,Daisy Communications Ltd
7021,A2B Telecom Limited
7021,Business Broadcast Communications Ltd
7021,Cheers International Sales Limited
7021,Daisy Communications Ltd
7021,Eclipse Tel Limited
7021,Invomo Ltd
7021,Known Communications Limited
7021,Orbis Telecom
7021,Phone Co-Op Numbering Limited
7021,Proton Telecom Limited
7021,Rexcom Tech Limited
7021,Sound Advertising Ltd
7021,Swiftnet Ltd
7021,Switch Services Ltd
7021,TalkTalk Communications Limited
7021,Think Telecom Solutions Limited
7021,Wavecrest (UK) Ltd
7022,2Communications Limited
7022,Danemere Street Creative
7022,Hospedia Limited
7022,Skytel Ltd
7022,T.T.N.C. Limited
7023,2Communications Limited
7023,Danemere Street Creative
7023,Hospedia Limited
7023,i-Net Communications Group Plc
7023,Instant Communication Limited
7023,Linear Telecoms Limited
7023,Magrathea Telecommunications Limited
7023,Numbers Plus Ltd
7023,Phone Co-Op Numbering Limited
7023,Sky Premium Telecom Limited
7023,Sound Advertising Ltd
7023,TeleMagic Ltd
7023,TeslaOne Limited
7024,Danemere Street Creative
7024,Global 1 Limited
7024,Magrathea Telecommunications Limited
7024,Rhodium Telecom Limited
7024,T.T.N.C. Limited
7025,Danemere Street Creative
7025,Rhodium Telecom Limited
7025,T.T.N.C. Limited
7026,Call Telecom Ltd
7026,Cloud9 Communications Limited
7026,Core Telecom Limited
7026,Global 1 Limited
7026,Rhodium Telecom Limited
7027,Call Telecom Ltd
7027,Rhodium Telecom Limited
7028,Business Broadcast Communications Ltd
7028,Cheers International Sales Limited
7028,COLT Technology Services
7028,Eclipse Tel Limited
7028,Jessico Networks Ltd
7028,Known Communications Limited
7028,Netscalibur UK Limited
7028,Syntec UK Ltd
7028,Virtual Universe Ltd
7029,Business Broadcast Communications Ltd
7029,Cable and Wireless (Energis)
7029,Cheers International Sales Limited
7029,Citrus Telecommunications Ltd
7029,COLT Technology Services
7029,Edge Telecom Limited
7029,Promotions4All Ltd
7029,Relax Telecom Plc
7029,Sound Advertising Ltd
7029,Talk Telecom Limited
7029,Titanium Limited
7030,Affiniti Integrated Solutions Ltd
7030,Atlas Interactive Group Limited
7030,Global Crossing (UK) Ltd
7030,Medius Networks Limited
7030,Net 366.Com Limited
7030,Nexus Telecommunications plc
7030,Skycom Ltd
7030,TalkTalk Communications Limited
7031,BSKYB LLU Assets Limited
7031,Callagenix Ltd
7031,Edge Telecom Limited
7031,Magrathea Telecommunications Limited
7031,Nodemax Limited
7031,Sala Limited
7031,Talk Telecom Limited
7032,Inclarity plc
7032,Sound Advertising Ltd
7033,24 Seven Communications Ltd
7033,Cable & Wireless UK
7033,Cheers International Sales Limited
7033,Modus Telecom Ltd
7033,Sound Advertising Ltd
7033,Xero 9 Limited
7034,Cheers International Sales Limited
7034,Interact Solutions Limited
7034,IPV6 Limited
7034,M P Tanner Limited t/a FIO Telecom
7034,Media Telecom Ltd
7034,Modus Telecom Ltd
7034,Premium O Limited
7034,Subhan Universal Limited
7034,Telappliant Ltd
7034,Telecom2 Ltd
7035,24 Seven Communications Ltd
7035,Cheers International Sales Limited
7035,IPV6 Limited
7035,Jessico Networks Ltd
7035,M P Tanner Limited t/a FIO Telecom
7035,Magrathea Telecommunications Limited
7035,Media Telecom Ltd
7035,Open Telecom International Ltd
7035,Subhan Universal Limited
7035,Suretec Systems Ltd
7035,Xero 9 Limited
7036,24 Seven Communications Ltd
7036,Cheers International Sales Limited
7036,Modus Telecom Ltd
7036,Oxygen8 Communications UK Limited
7036,Telecom Essex Ltd
7037,Media Telecom Ltd
7038,Core Telecom Limited
7038,Sound Advertising Ltd
7039,Cheers International Sales Limited
7039,Cloud9 Communications Limited
7039,Digital Mail Limited
7039,Edge Telecom Limited
7039,Firstsound Ltd
7039,Invomo Ltd
7039,Starcomm Ltd
7039,Talk Telecom Limited
7039,Telecom2 Ltd
7040,Business Broadcast Communications Ltd
7040,Digital Mail Limited
7040,Global 1 Limited
7040,Hospedia Limited
7040,IPV6 Limited
7040,Phone Buddy Limited
7040,Promotions4All Ltd
7040,Proton Telecom Limited
7040,TalkTalk Communications Limited
7040,TelXL Ltd
7041,Cable and Wireless (Energis)
7041,Cheers International Sales Limited
7041,Danemere Street Creative
7041,Edge Telecom Limited
7041,Hospedia Limited
7041,Invomo Ltd
7042,Core Telecom Limited
7042,Invomo Ltd
7042,Media Telecom Ltd
7042,Sky Telecom Limited
7042,Virtual Universe Ltd
7042,YAC Ltd
7043,Affiniti Integrated Solutions Ltd
7043,Call Sciences Limited
7043,Citrus Telecommunications Ltd
7043,Digital Mail Limited
7043,Inclarity plc
7043,Media Telecom Ltd
7043,Plus Telecom Limited
7043,T.T.N.C. Limited
7043,YAC Ltd
7044,Call Sciences Limited
7044,Connect Telecom UK Ltd
7044,Danemere Street Creative
7044,Edge Telecom Limited
7044,Jtec UK Limited
7044,Medius Networks Limited
7044,TalkTalk Communications Limited
7044,YAC Ltd
7045,24 Seven Communications Ltd
7045,Cheers International Sales Limited
7045,Media Telecom Ltd
7045,Open Telecom International Ltd
7045,Oxygen8 Communications UK Limited
7045,Subhan Universal Limited
7045,Switch Services Ltd
7045,Xero 9 Limited
7046,Cheers International Sales Limited
7046,Global Crossing (UK) Ltd
7046,Hospedia Limited
7046,IV Response Limited
7046,Prodigy Internet Ltd
7046,Starcomm Ltd
7047,24 Seven Communications Ltd
7047,Cheers International Sales Limited
7047,Modus Telecom Ltd
7047,Oxygen8 Communications UK Limited
7047,T.T.N.C. Limited
7047,Xero 9 Limited
7048,24 Seven Communications Ltd
7048,Cheers International Sales Limited
7048,Oxygen8 Communications UK Limited
7048,T.T.N.C. Limited
7048,Xero 9 Limited
7049,
7049,2Communications Limited
7049,COLT Technology Services
7049,Fused Networks Limited
7049,Jessico Networks Ltd
7049,Media Telecom Ltd
7049,Reality Telecom plc
7049,Switch Services Ltd
7049,Teledesign Ltd
7049,TG Support Limited
7049,Tuxtel Ltd
7049,Xero 9 Limited
7050,Daisy Communications Ltd
7052,Inclarity plc
7052,Jtec UK Limited
7052,Media Telecom Ltd
7052,Oxygen8 Communications UK Limited
7052,Plus Telecom Limited
7052,YAC Ltd
7053,Jtec UK Limited
7053,Media Telecom Ltd
7053,Simwood eSMS Limited
7053,YAC Ltd
7054,A2B Telecom Limited
7054,Daisy Communications Ltd
7054,Jtec UK Limited
7054,PD Media Limited
7054,Simwood eSMS Limited
7054,T.T.N.C. Limited
7055,2-Sell-It Ltd
7055,FleXtel Limited
7055,Jtec UK Limited
7055,PD Media Limited
7055,T.T.N.C. Limited
7055,Telecom2 Ltd
7056,Capital Marketing Resources UK Limited
7056,Cheers International Sales Limited
7056,Core Telecom Limited
7056,Digitech Solutions Global Limited
7056,Mars Communications Limited
7056,Net Solutions Europe Limited
7057,Cheers International Sales Limited
7057,Core Telecom Limited
7057,Digitech Solutions Global Limited
7057,Magrathea Telecommunications Limited
7057,Nodemax Limited
7057,Phone Buddy Limited
7057,Plus Telecom Limited
7057,Proton Telecom Limited
7057,Virtual Universe Ltd
7058,Business Broadcast Communications Ltd
7058,Cheers International Sales Limited
7058,City Interactive Media Limited
7058,Core Telecom Limited
7058,M P Tanner Limited t/a FIO Telecom
7058,Phone Buddy Limited
7058,Promotions4All Ltd
7058,Vortex Telecom Ltd
7059,Cable & Wireless UK
7059,Daisy Communications Ltd
7059,Starcomm Ltd
7059,Teledesign Ltd
7059,TG Support Limited
7059,YAC Ltd
7060,Telefonica UK Limited
7061,BSKYB LLU Assets Limited
7061,Gamma Telecom Holdings Ltd
7061,Invomo Ltd
7061,Plus Telecom Limited
7061,Sky Telecom Limited
7061,Windsor Telecom Plc
7062,Core Telecom Limited
7062,Elephant Talk Communications PRS U.K Limited
7062,Invoco Ltd
7062,Jtec UK Limited
7062,Nodemax Limited
7062,Phone Buddy Limited
7062,Proton Telecom Limited
7062,Rexcom Tech Limited
7062,Tuxtel Ltd
7062,Zap Communications Limited
7063,Core Telecom Limited
7063,Nexus Telecommunications plc
7063,Plus Telecom Limited
7064,Jtec UK Limited
7064,Net 366.Com Limited
7064,QX Telecom Ltd
7064,Sky Telecom Limited
7064,Starcomm Ltd
7064,Subhan Universal Limited
7065,Media Telecom Ltd
7065,Sky Telecom Limited
7065,Subhan Universal Limited
7065,Teledesign Ltd
7066,Media Telecom Ltd
7067,Inclarity plc
7067,Jtec UK Limited
7067,Media Telecom Ltd
7068,Instant Communication Limited
7068,Jtec UK Limited
7068,Media Telecom Ltd
7068,Subhan Universal Limited
7068,Voicetec Systems Ltd
7069,Assume Nothing Limited
7069,Inclarity plc
7069,Nexus Telecommunications plc
7069,Resource Utilities Limited
7069,TalkTalk Communications Limited
7069,Telco Global Networks Limited
7069,Titanium Limited
7070,24 Seven Communications Ltd
7070,Connect Telecom UK Ltd
7070,Invomo Ltd
7070,QiComm Ltd
7070,Skycom Ltd
7070,Starcomm Ltd
7070,TalkTalk Communications Limited
7070,Telsis Systems Ltd
7071,Invomo Ltd
7071,Starcomm Ltd
7071,Talk Telecom Limited
7072,QX Telecom Ltd
7072,Sky Telecom Limited
7072,Teledesign Ltd
7073,Plus Telecom Limited
7073,Starcomm Ltd
7074,Vodafone Ltd
7076,Cheers International Sales Limited
7076,Media Telecom Ltd
7077,24 Seven Communications Ltd
7077,Digital Mail Limited
7077,Inclarity plc
7077,Resource Utilities Limited
7077,Syntec UK Ltd
7077,Telecoms World Plc
7077,Vital Phone Limited
7077,Vodafone Business Solutions Limited
7078,Cheers International Sales Limited
7078,Jtec UK Limited
7078,Media Telecom Ltd
7078,Nationwide Telephone Assistance Ltd
7078,Syntec UK Ltd
7079,Cheers International Sales Limited
7079,Eclipse Tel Limited
7079,Invomo Ltd
7079,Premier Voicemail Ltd
7079,Promotions4All Ltd
7079,Starcomm Ltd
7079,Switch Services Ltd
7079,Vortex Telecom Ltd
7079,Yim Siam Telecom
7080,BSKYB LLU Assets Limited
7080,Cheers International Sales Limited
7080,Global Crossing (UK) Ltd
7080,IPV6 Limited
7080,PageOne Communications Ltd
7080,Primus Telecommunications
7080,Promotions4All Ltd
7080,Proton Telecom Limited
7080,Red Squared Ltd
7080,Virtual Universe Ltd
7081,24 Seven Communications Ltd
7081,Affiniti Integrated Solutions Ltd
7081,Atlas Interactive Group Limited
7081,BSKYB LLU Assets Limited
7081,Hospedia Limited
7081,IV Response Limited
7081,PageOne Communications Ltd
7081,Sky Telecom Limited
7081,Tiscali UK Limited
7082,ETC Telecom Ltd
7082,i-Net Communications Group Plc
7082,Starcomm Ltd
7083,ETC Telecom Ltd
7084,Assume Nothing Limited
7084,Coralbridge Ltd
7084,ETC Telecom Ltd
7084,Invomo Ltd
7084,Mars Communications Limited
7085,CFL Communications Limited
7085,Content Guru Ltd
7085,Mars Communications Limited
7085,Sound Advertising Ltd
7085,Switch Services Ltd
7085,Teledesign Ltd
7085,Virtual Universe Ltd
7086,4D Interactive Ltd
7086,Cheers International Sales Limited
7086,Invest UK Limited
7086,IPV6 Limited
7086,Magrathea Telecommunications Limited
7086,Plus Telecom Limited
7086,Proton Telecom Limited
7086,Rexcom Tech Limited
7086,Sound Advertising Ltd
7086,Supreme Connect Limited
7086,Suretec Systems Ltd
7086,Telephone Box Limited
7086,Vortex Telecom Ltd
7087,Firstsound Ltd
7087,i-Net Communications Group Plc
7087,One Network Limited
7087,Proton Telecom Limited
7087,Safety In Numbers.co.uk Limited
7087,Sound Advertising Ltd
7087,Spacetel UK Ltd
7087,Supreme Connect Limited
7087,TeleSurf Limited
7087,Vertical Systems Limited
7087,Voicetec Systems Ltd
7088,
7088,Broadcast Telecom Ltd
7088,Jessico Networks Ltd
7088,Numbers Telecom Limited
7088,Reading Telecom Limited
7088,Sentiro Limited
7088,Spacetel UK Ltd
7088,Supreme Connect Limited
7088,TG Support Limited
7088,Tismi BV
7088,Tuxtel Ltd
7089,Cheers International Sales Limited
7089,Coralbridge Ltd
7089,IV Response Limited
7089,M P Tanner Limited t/a FIO Telecom
7089,Media Telecom Ltd
7089,Promotions4All Ltd
7089,Sky Telecom Limited
7089,Starcomm Ltd
7089,Suretec Systems Ltd
7089,Switch Services Ltd
7089,TG Support Limited
7089,Yim Siam Telecom
7090,Daisy Communications Ltd
7090,InTechnology Plc
7090,Numbergroup Network Ltd
7090,Starcomm Ltd
7090,Switch Services Ltd
7090,Windsor Telecom Plc
7091,Affiniti Integrated Solutions Ltd
7092,YAC Ltd
7093,Cheers International Sales Limited
7093,Net Solutions Europe Limited
7093,TG Support Limited
7094,Cheers International Sales Limited
7094,i-Net Communications Group Plc
7094,Magrathea Telecommunications Limited
7094,Syntec UK Ltd
7094,Telsis Systems Ltd
7094,Xero 9 Limited
7094,Yim Siam Telecom
7096,2PM Technologies Ltd
7096,Syntec UK Ltd
7096,Titanium Limited
7096,Xero 9 Limited
7096,YAC Ltd
7099,24 Seven Communications Ltd
7099,Cloud9 Communications Limited
7099,Hutchison 3G UK Ltd
7099,Premium O Limited
7099,QiComm Ltd
7099,Sala Limited
7099,Switch Services Ltd
7099,Telecom2 Ltd
7099,Teledesign Ltd
7099,Yim Siam Telecom
7400,Hutchison 3G UK Ltd
7401,Hutchison 3G UK Ltd
7402,Hutchison 3G UK Ltd
7403,Hutchison 3G UK Ltd
7404,Lycamobile UK Limted
7405,Lycamobile UK Limted
7406,08Direct Limited
7406,24 Seven Communications Ltd
7406,CardBoardFish
7406,Cheers International Sales Limited
7406,Telecom2 Ltd
7406,TG Support Limited
7406,Titanium Limited
7406,Vortex Telecom Ltd
7407,Vodafone Ltd
7408,Truphone Ltd
7409,Orange
7410,Orange
7411,Hutchison 3G UK Ltd
7412,Hutchison 3G UK Ltd
7413,Hutchison 3G UK Ltd
7414,Hutchison 3G UK Ltd
7415,Everything Everywhere Limited (TM)
7416,Orange
7417,CardBoardFish
7417,Hutchison 3G UK Ltd
7417,Interact Solutions Limited
7417,Lycamobile UK Limted
7417,Proton Telecom Limited
7417,Rexcom Tech Limited
7417,Truphone Ltd
7417,UPA Telecom Ltd
7418,Ace Call Limited
7418,Bellingham Telecommunications Limited
7418,DXI Easycall Limited
7418,Eclipse Tel Limited
7418,Manx Telecom
7418,Telecom North America Mobile Inc
7418,Teleena UK Limited
7418,TG Support Limited
7418,Tismi BV
7419,Orange
7420,Orange
7421,Orange
7422,Orange
7423,Vodafone Ltd
7424,Lycamobile UK Limted
7425,Vodafone Ltd
7426,Hutchison 3G UK Ltd
7427,Hutchison 3G UK Ltd
7428,Hutchison 3G UK Ltd
7429,Hutchison 3G UK Ltd
7430,Telefonica UK Limited
7431,Telefonica UK Limited
7432,Everything Everywhere Limited (TM)
7433,Everything Everywhere Limited (TM)
7434,Everything Everywhere Limited (TM)
7435,Vodafone Ltd
7436,Vodafone Ltd
7437,Vodafone Ltd
7438,Lycamobile UK Limted
7439,TalkTalk Communications Limited
7439,Withheld
7440,Cloud9 Communications Limited
7440,Lycamobile UK Limted
7441,Andrews & Arnold Ltd
7441,Cable & Wireless UK
7441,Core Telecom Limited
7441,JSC Ingenium (UK) Limited
7441,Sound Advertising Ltd
7441,Stour Marine Limited
7441,Synectiv Ltd
7441,Voxbone SA
7441,Zap Communications Limited
7442,Vodafone Ltd
7443,Vodafone Ltd
7444,Vodafone Ltd
7445,Hutchison 3G UK Ltd
7446,Hutchison 3G UK Ltd
7447,Hutchison 3G UK Ltd
7448,Lycamobile UK Limted
7449,Hutchison 3G UK Ltd
7450,Hutchison 3G UK Ltd
7451,Mundio Mobile Limited
7451,Premium O Limited
7451,Tismi BV
7451,UK Broadband Limited
7452,Manx Telecom
7452,Withheld
7453,Hutchison 3G UK Ltd
7454,Hutchison 3G UK Ltd
7455,Hutchison 3G UK Ltd
7456,Hutchison 3G UK Ltd
7457,CardBoardFish
7457,Fluenta Ltd
7457,Marathon Telecom Limited
7457,Mundio Mobile Limited
7457,Spacetel UK Ltd
7457,Voicetec Systems Ltd
7458,
7459,Lycamobile UK Limted
7460,Hutchison 3G UK Ltd
7461,Telefonica UK Limited
7462,Hutchison 3G UK Ltd
7463,Hutchison 3G UK Ltd
7465,
7465,Mundio Mobile Limited
7466,Lycamobile UK Limted
7500,Vodafone Ltd
7501,Vodafone Ltd
7502,Vodafone Ltd
7503,Vodafone Ltd
7504,Everything Everywhere Limited (TM)
7505,Everything Everywhere Limited (TM)
7506,Everything Everywhere Limited (TM)
7507,Everything Everywhere Limited (TM)
7508,Everything Everywhere Limited (TM)
7509,Jersey Telecom
7509,Withheld
7510,Telefonica UK Limited
7511,Telefonica UK Limited
7512,Telefonica UK Limited
7513,Telefonica UK Limited
7514,Telefonica UK Limited
7515,Telefonica UK Limited
7516,Telefonica UK Limited
7517,Telefonica UK Limited
7518,Telefonica UK Limited
7519,Telefonica UK Limited
7520,Coralbridge Ltd
7520,Core Communication Services Ltd
7520,D2See Limited
7520,Esendex Limited
7520,Invomo Ltd
7520,Mundio Mobile Limited
7520,OnePhone (UK) Ltd
7520,Subhan Universal Limited
7520,Teledesign Ltd
7520,Tismi BV
7521,Telefonica UK Limited
7522,Telefonica UK Limited
7523,Telefonica UK Limited
7524,Withheld
7525,Telefonica UK Limited
7526,Telefonica UK Limited
7527,Orange
7528,Orange
7529,Orange
7530,Orange
7531,Orange
7532,Orange
7532,Withheld
7533,Hutchison 3G UK Ltd
7534,Everything Everywhere Limited (TM)
7535,Everything Everywhere Limited (TM)
7536,Orange
7537,Awayphone Ltd
7537,CFL Communications Limited
7537,Sound Advertising Ltd
7537,Stour Marine Limited
7537,Swiftnet Ltd
7537,T.T.N.C. Limited
7537,Vodafone Ltd
7537,Wavecrest (UK) Ltd
7538,Everything Everywhere Limited (TM)
7539,Everything Everywhere Limited (TM)
7540,Telefonica UK Limited
7541,Telefonica UK Limited
7542,Telefonica UK Limited
7543,Telefonica UK Limited
7544,Telefonica UK Limited
7545,Telefonica UK Limited
7546,Telefonica UK Limited
7547,Telefonica UK Limited
7548,Telefonica UK Limited
7549,Telefonica UK Limited
7550,Everything Everywhere Limited (TM)
7551,Vodafone Ltd
7552,Vodafone Ltd
7553,Vodafone Ltd
7554,Vodafone Ltd
7555,Vodafone Ltd
7556,Orange
7557,Vodafone Ltd
7559,Confabulate Limited
7559,Core Telecom Limited
7559,Globecom International Limited
7559,IPV6 Limited
7559,LegendTel LLC
7559,Lleida.net Serveis Telematics Limited
7559,Mars Communications Limited
7559,Nodemax Limited
7559,Resilient Networks Plc
7559,Truphone Ltd
7560,Telefonica UK Limited
7561,Telefonica UK Limited
7562,Telefonica UK Limited
7563,Telefonica UK Limited
7564,Telefonica UK Limited
7565,Telefonica UK Limited
7566,Telefonica UK Limited
7567,Telefonica UK Limited
7568,Telefonica UK Limited
7569,Telefonica UK Limited
7570,Vodafone Ltd
7571,09 Mobile Ltd
7571,Alliance Technologies LLC
7571,Withheld
7572,Everything Everywhere Limited (TM)
7573,Everything Everywhere Limited (TM)
7574,Everything Everywhere Limited (TM)
7575,Hutchison 3G UK Ltd
7576,Hutchison 3G UK Ltd
7577,Hutchison 3G UK Ltd
7578,Hutchison 3G UK Ltd
7579,Orange
7580,Orange
7581,Orange
7582,Orange
7583,Orange
7584,Vodafone Ltd
7585,Vodafone Ltd
7586,Vodafone Ltd
7587,Vodafone Ltd
7588,Hutchison 3G UK Ltd
7589,Moonshado Inc
7589,Mundio Mobile Limited
7589,Oxygen8 Communications UK Limited
7589,Test2date B.V
7589,Yim Siam Telecom
7590,Telefonica UK Limited
7591,Telefonica UK Limited
7592,Telefonica UK Limited
7593,Telefonica UK Limited
7594,Telefonica UK Limited
7595,Telefonica UK Limited
7596,Telefonica UK Limited
7597,Telefonica UK Limited
7598,Telefonica UK Limited
7599,Telefonica UK Limited
7600,
7600,24 Seven Communications Ltd
7600,PageOne Communications Ltd
7600,Relax Telecom Plc
7600,Sound Advertising Ltd
7602,
7602,Relax Telecom Plc
7602,Telefonica UK Limited
7623,PageOne Communications Ltd
7624,Cable and Wireless Isle of Man Limited
7624,Manx Telecom
7625,Telefonica UK Limited
7626,Telefonica UK Limited
7640,
7640,Core Telecom Limited
7640,M P Tanner Limited t/a FIO Telecom
7640,PageOne Communications Ltd
7640,Telecom2 Ltd
7641,
7641,Orange
7643,
7643,Yim Siam Telecom
7644,
7644,Media Telecom Ltd
7644,Proton Telecom Limited
7644,Telefonica UK Limited
7644,Titanium Limited
7654,PageOne Communications Ltd
7659,PageOne Communications Ltd
7659,Vodafone Ltd
7660,24 Seven Communications Ltd
7660,PageOne Communications Ltd
7660,Plus Telecom Limited
7660,Premium O Limited
7661,PageOne Communications Ltd
7662,Premium O Limited
7663,
7663,Relax Telecom Plc
7663,Switch Services Ltd
7663,Syntec UK Ltd
7663,Vodafone Ltd
7666,24 Seven Communications Ltd
7666,M P Tanner Limited t/a FIO Telecom
7666,Vodafone Ltd
7669,
7669,Cheers International Sales Limited
7669,Confabulate Limited
7669,Telefonica UK Limited
7677,
7677,24 Seven Communications Ltd
7677,Core Telecom Limited
7677,Relax Telecom Plc
7677,Telsis Systems Ltd
7681,PageOne Communications Ltd
7693,Telefonica UK Limited
7699,Vodafone Ltd
7700,Cable & Wireless Jersey Limited
7700,Cloud9 Communications Limited
7700,Nationwide Telephone Assistance Ltd
7701,Telefonica UK Limited
7702,Telefonica UK Limited
7703,Telefonica UK Limited
7704,Telefonica UK Limited
7705,Telefonica UK Limited
7706,Telefonica UK Limited
7707,Telefonica UK Limited
7708,Telefonica UK Limited
7709,Telefonica UK Limited
7710,Telefonica UK Limited
7711,Telefonica UK Limited
7712,Telefonica UK Limited
7713,Telefonica UK Limited
7714,Telefonica UK Limited
7715,Telefonica UK Limited
7716,Telefonica UK Limited
7717,Vodafone Ltd
7718,Telefonica UK Limited
7719,Telefonica UK Limited
7720,Telefonica UK Limited
7721,Vodafone Ltd
7722,Everything Everywhere Limited (TM)
7723,Hutchison 3G UK Ltd
7724,Telefonica UK Limited
7725,Telefonica UK Limited
7726,Everything Everywhere Limited (TM)
7727,Hutchison 3G UK Ltd
7728,Hutchison 3G UK Ltd
7729,Telefonica UK Limited
7730,Telefonica UK Limited
7731,Telefonica UK Limited
7732,Telefonica UK Limited
7733,Vodafone Ltd
7734,Telefonica UK Limited
7735,Hutchison 3G UK Ltd
7736,Telefonica UK Limited
7737,Hutchison 3G UK Ltd
7738,Telefonica UK Limited
7739,Telefonica UK Limited
7740,Telefonica UK Limited
7741,Vodafone Ltd
7742,Telefonica UK Limited
7743,Telefonica UK Limited
7744,Core Communication Services Ltd
7745,Telefonica UK Limited
7746,Telefonica UK Limited
7747,Vodafone Ltd
7748,Vodafone Ltd
7749,Telefonica UK Limited
7750,Telefonica UK Limited
7751,Telefonica UK Limited
7752,Telefonica UK Limited
7753,Telefonica UK Limited
7754,Telefonica UK Limited
7755,Core Communication Services Ltd
7756,Telefonica UK Limited
7757,Everything Everywhere Limited (TM)
7758,Everything Everywhere Limited (TM)
7759,Telefonica UK Limited
7760,Vodafone Ltd
7761,Telefonica UK Limited
7762,Telefonica UK Limited
7763,Telefonica UK Limited
7764,Telefonica UK Limited
7765,Vodafone Ltd
7766,Vodafone Ltd
7767,Vodafone Ltd
7768,Vodafone Ltd
7769,Vodafone Ltd
7770,Vodafone Ltd
7771,Vodafone Ltd
7772,Orange
7773,Orange
7774,Vodafone Ltd
7775,Vodafone Ltd
7776,Vodafone Ltd
7777,BT
7778,Vodafone Ltd
7779,Orange
7780,Vodafone Ltd
7781,Cable and Wireless Guernsey Limited
7782,Hutchison 3G UK Ltd
7783,Telefonica UK Limited
7784,Telefonica UK Limited
7785,Vodafone Ltd
7786,Vodafone Ltd
7787,Vodafone Ltd
7788,Vodafone Ltd
7789,Vodafone Ltd
7790,Orange
7791,Orange
7792,Orange
7793,Telefonica UK Limited
7794,Orange
7795,Vodafone Ltd
7796,Vodafone Ltd
7797,Jersey Telecom
7797,Withheld
7798,Vodafone Ltd
7799,Vodafone Ltd
7800,Orange
7801,Telefonica UK Limited
7802,Telefonica UK Limited
7803,Telefonica UK Limited
7804,Everything Everywhere Limited (TM)
7805,Orange
7806,Everything Everywhere Limited (TM)
7807,Orange
7808,Telefonica UK Limited
7809,Telefonica UK Limited
7810,Vodafone Ltd
7811,Orange
7812,Orange
7813,Orange
7814,Orange
7815,Orange
7816,Orange
7817,Orange
7818,Vodafone Ltd
7819,Telefonica UK Limited
7820,Telefonica UK Limited
7821,Telefonica UK Limited
7822,Cable & Wireless UK
7822,Cheers International Sales Limited
7822,FleXtel Limited
7822,Oxygen8 Communications UK Limited
7822,Swiftnet Ltd
7822,TalkTalk Communications Limited
7822,Telephony Services Limited
7822,Vectone Network Limited
7823,Vodafone Ltd
7824,Vodafone Ltd
7825,Vodafone Ltd
7826,Vodafone Ltd
7827,Vodafone Ltd
7828,Hutchison 3G UK Ltd
7829,Jersey Airtel  Limited
7830,Hutchison 3G UK Ltd
7831,Vodafone Ltd
7832,Hutchison 3G UK Ltd
7833,Vodafone Ltd
7834,Telefonica UK Limited
7835,Telefonica UK Limited
7836,Vodafone Ltd
7837,Orange
7838,Hutchison 3G UK Ltd
7839,Cable and Wireless Guernsey Limited
7839,Guernsey Airtel Limited
7840,Telefonica UK Limited
7841,Telefonica UK Limited
7842,Telefonica UK Limited
7843,Telefonica UK Limited
7844,Telefonica UK Limited
7845,Telefonica UK Limited
7846,Hutchison 3G UK Ltd
7847,Everything Everywhere Limited (TM)
7848,Hutchison 3G UK Ltd
7849,Telefonica UK Limited
7850,Telefonica UK Limited
7851,Telefonica UK Limited
7852,Everything Everywhere Limited (TM)
7853,Hutchison 3G UK Ltd
7854,Orange
7855,Orange
7856,Telefonica UK Limited
7857,Telefonica UK Limited
7858,Telefonica UK Limited
7859,Hutchison 3G UK Ltd
7860,Telefonica UK Limited
7861,Hutchison 3G UK Ltd
7862,Hutchison 3G UK Ltd
7863,Hutchison 3G UK Ltd
7864,Switch Services Ltd
7864,Telefonica UK Limited
7865,Hutchison 3G UK Ltd
7866,Orange
7867,Vodafone Ltd
7868,Hutchison 3G UK Ltd
7869,Hutchison 3G UK Ltd
7870,Orange
7871,Telefonica UK Limited
7872,Cloud9 Communications Limited
7872,Sky Telecom Limited
7872,Telefonica UK Limited
7873,Routo Telecommunications Limited
7873,Telefonica UK Limited
7874,Callax Limited
7874,Citrus Telecommunications Ltd
7874,Telefonica UK Limited
7875,Orange
7876,Vodafone Ltd
7877,Hutchison 3G UK Ltd
7878,Hutchison 3G UK Ltd
7879,Vodafone Ltd
7880,Vodafone Ltd
7881,Vodafone Ltd
7882,Hutchison 3G UK Ltd
7883,Hutchison 3G UK Ltd
7884,Vodafone Ltd
7885,Telefonica UK Limited
7886,Hutchison 3G UK Ltd
7887,Vodafone Ltd
7888,Hutchison 3G UK Ltd
7889,Telefonica UK Limited
7890,Orange
7891,Orange
7892,Edge Telecom Limited
7892,FleXtel Limited
7892,HAY SYSTEMS LIMITED
7892,Mundio Mobile Limited
7892,Telefonica UK Limited
7893,24 Seven Communications Ltd
7893,Citrus Telecommunications Ltd
7893,Magrathea Telecommunications Limited
7893,Telefonica UK Limited
7893,Telephony Services Limited
7893,Yim Siam Telecom
7894,Telefonica UK Limited
7895,Telefonica UK Limited
7896,Orange
7897,Hutchison 3G UK Ltd
7898,Hutchison 3G UK Ltd
7899,Vodafone Ltd
7900,Vodafone Ltd
7901,Vodafone Ltd
7902,Telefonica UK Limited
7903,Everything Everywhere Limited (TM)
7904,Everything Everywhere Limited (TM)
7905,Everything Everywhere Limited (TM)
7906,Everything Everywhere Limited (TM)
7907,Telefonica UK Limited
7908,Everything Everywhere Limited (TM)
7909,Vodafone Ltd
7910,Everything Everywhere Limited (TM)
7911,
7911,24 Seven Communications Ltd
7911,Marathon Telecom Limited
7911,Wave Telecom Limited
7912,Telefonica UK Limited
7913,Everything Everywhere Limited (TM)
7914,Everything Everywhere Limited (TM)
7915,Hutchison 3G UK Ltd
7916,Hutchison 3G UK Ltd
7917,Vodafone Ltd
7918,Vodafone Ltd
7919,Vodafone Ltd
7920,Vodafone Ltd
7921,Telefonica UK Limited
7922,Telefonica UK Limited
7923,Telefonica UK Limited
7924,Cloud9 Communications Limited
7924,Manx Telecom
7925,Telefonica UK Limited
7926,Telefonica UK Limited
7927,Telefonica UK Limited
7928,Telefonica UK Limited
7929,Orange
7930,Everything Everywhere Limited (TM)
7931,Everything Everywhere Limited (TM)
7932,Everything Everywhere Limited (TM)
7933,Telefonica UK Limited
7934,Telefonica UK Limited
7935,Telefonica UK Limited
7936,Telefonica UK Limited
7937,Jersey Telecom
7938,Telefonica UK Limited
7939,Everything Everywhere Limited (TM)
7940,Everything Everywhere Limited (TM)
7941,Everything Everywhere Limited (TM)
7942,Everything Everywhere Limited (TM)
7943,Everything Everywhere Limited (TM)
7944,Everything Everywhere Limited (TM)
7945,Everything Everywhere Limited (TM)
7946,Everything Everywhere Limited (TM)
7947,Everything Everywhere Limited (TM)
7948,Everything Everywhere Limited (TM)
7949,Everything Everywhere Limited (TM)
7950,Everything Everywhere Limited (TM)
7951,Everything Everywhere Limited (TM)
7952,Everything Everywhere Limited (TM)
7953,Everything Everywhere Limited (TM)
7954,Everything Everywhere Limited (TM)
7955,Telefonica UK Limited
7956,Everything Everywhere Limited (TM)
7957,Everything Everywhere Limited (TM)
7958,Everything Everywhere Limited (TM)
7959,Everything Everywhere Limited (TM)
7960,Everything Everywhere Limited (TM)
7961,Everything Everywhere Limited (TM)
7962,Everything Everywhere Limited (TM)
7963,Everything Everywhere Limited (TM)
7964,Orange
7965,Orange
7966,Orange
7967,Orange
7968,Orange
7969,Orange
7970,Orange
7971,Orange
7972,Orange
7973,Orange
7974,Orange
7975,Orange
7976,Orange
7977,Orange
7978,Callax Limited
7978,Cheers International Sales Limited
7978,Cloud9 Communications Limited
7978,IV Response Limited
7978,Oxygen8 Communications UK Limited
7978,QX Telecom Ltd
7978,TeleWare PLC
7978,Truphone Ltd
7978,Vectone Network Limited
7979,Vodafone Ltd
7980,Orange
7981,Everything Everywhere Limited (TM)
7982,Everything Everywhere Limited (TM)
7983,Everything Everywhere Limited (TM)
7984,Everything Everywhere Limited (TM)
7985,Everything Everywhere Limited (TM)
7986,Everything Everywhere Limited (TM)
7987,Everything Everywhere Limited (TM)
7988,Hutchison 3G UK Ltd
7989,Orange
7990,Vodafone Ltd
7999,Telefonica UK Limited
DATA;

	/*
	* Must begin with 0, +44 or 44
	* Then there must be the digit 7, followed by 3 digits (0-9) because this is the format of the above ranges
	* Then there must be 6, 7 or 8 digits after this (0-9) (E.164 says the max length of a number is 15 digits minus the length of the country code (44 is 2 digits) which totals 13 digits for a UK number)
	*/

	if (preg_match('/^(?:0|\+?44)(7[0-9]{3})[0-9]{6,8}$/', $msisdn, $matches)) {
		if (preg_match('/'.$matches[1].',(.+)/i', $data, $matches)) {
			$netname = trim($matches[1]);
			$netid = '98';

			if ($netname == 'Telefonica UK Limited') {$netid = '10'; $netname = 'O2';}
			if ($netname == 'Vodafone Ltd') {$netid = '15'; $netname = 'Vodafone';}
			if ($netname == 'Hutchison 3G UK Ltd') {$netid = '20'; $netname = '3G UK';}
			if ($netname == 'Everything Everywhere Limited (TM)') {$netid = '30'; $netname = 'T Mobile';}
			if ($netname == 'Orange') {$netid = '33'; $netname = 'Orange';}

			if ($netid == '98') {$netname = 'Other';}

			return(array($netid, $netname));
		}
	}

	return(array('99', 'unknown'));
}

function ofcomname($mnc) {

	settype($mnc, "integer");

	if ($mnc == 0)  {$mncname = 'BT';} else 
	if ($mnc == 1)  {$mncname = 'Mundio Mobile Limited';} else 
	if ($mnc == 2)  {$mncname = 'Telefonica UK Limited';} else 
	if ($mnc == 3)  {$mncname = 'Jersey Airtel  Limited';} else 
	if ($mnc == 4)  {$mncname = 'FMS Solutions Limited';} else 
	if ($mnc == 5)  {$mncname = 'COLT Mobile Telecommunications Limited';} else 
	if ($mnc == 6)  {$mncname = 'Internet Computer Bureau Limited';} else 
	if ($mnc == 7)  {$mncname = 'Vodafone Ltd (C&W)';} else 
	if ($mnc == 8)  {$mncname = 'BT OnePhone Limited';} else 
	if ($mnc == 9)  {$mncname = 'Tismi BV';} else 
	if ($mnc == 10) {$mncname = 'Telefonica UK Limited';} else 
	if ($mnc == 11) {$mncname = 'Telefonica UK Limited';} else 
	if ($mnc == 12) {$mncname = 'Network Rail Infrastructure Limited';} else 
	if ($mnc == 13) {$mncname = 'Network Rail Infrastructure Limited';} else 
	if ($mnc == 14) {$mncname = 'HAY SYSTEMS LIMITED';} else 
	if ($mnc == 15) {$mncname = 'Vodafone Uk Ltd';} else 
	if ($mnc == 16) {$mncname = 'TalkTalk Communications Limited';} else 
	if ($mnc == 17) {$mncname = 'FleXtel Limited';} else 
	if ($mnc == 18) {$mncname = 'Cloud9 Communications Limited';} else 
	if ($mnc == 19) {$mncname = 'TeleWare PLC';} else 
	if ($mnc == 20) {$mncname = 'Hutchison 3G UK Ltd';} else 
	if ($mnc == 22) {$mncname = 'Telesign Mobile Limited';} else 
	if ($mnc == 23) {$mncname = 'Icron Network Limited';} else 
	if ($mnc == 24) {$mncname = 'Stour Marine Limited';} else 
	if ($mnc == 25) {$mncname = 'Truphone Ltd';} else 
	if ($mnc == 26) {$mncname = 'Lycamobile UK Limited';} else 
	if ($mnc == 27) {$mncname = 'Teleena UK Limited';} else 
	if ($mnc == 28) {$mncname = 'Marathon Telecom Limited';} else 
	if ($mnc == 29) {$mncname = '(aq) Limited trading as aql';} else 
	if ($mnc == 30) {$mncname = 'EE Limited ( TM)';} else 
	if ($mnc == 31) {$mncname = 'EE Limited ( TM)';} else 
	if ($mnc == 32) {$mncname = 'EE Limited ( TM)';} else 
	if ($mnc == 33) {$mncname = 'Orange';} else 
	if ($mnc == 34) {$mncname = 'Orange';} else 
	if ($mnc == 35) {$mncname = 'JSC Ingenium (UK) Limited';} else 
	if ($mnc == 36) {$mncname = 'Sure (Isle of Man) Limited';} else 
	if ($mnc == 37) {$mncname = 'Synectiv Ltd';} else 
	if ($mnc == 38) {$mncname = 'Virgin Mobile Telecoms Limited';} else 
	if ($mnc == 39) {$mncname = 'SSE Energy Supply Limited';} else 
	if ($mnc == 50) {$mncname = 'Jersey Telecom';} else 
	if ($mnc == 51) {$mncname = 'UK Broadband Limited';} else 
	if ($mnc == 52) {$mncname = 'Shyam Telecom UK Ltd';} else 
	if ($mnc == 53) {$mncname = 'Limitless Mobile Ltd';} else 
	if ($mnc == 54) {$mncname = 'The Carphone Warehouse Limited';} else 
	if ($mnc == 55) {$mncname = 'Sure (Guernsey) Limited';} else 
	if ($mnc == 58) {$mncname = 'Manx Telecom';} else 
	if ($mnc == 76) {$mncname = 'BT';} else 
	if ($mnc == 78) {$mncname = 'Airwave Solutions Ltd';} else 
	if ($mnc == 86) {$mncname = 'EE Limited ( TM)';} else 
	if ($mnc == 00) {$mncname = 'Mundio Mobile Limited';} else 
	if ($mnc == 01) {$mncname = 'EE Limited ( TM)';} else 
	if ($mnc == 02) {$mncname = 'EE Limited ( TM)';} else 
	if ($mnc == 03) {$mncname = 'UK Broadband Limited';} else 
	if ($mnc == 77) {$mncname = 'BT';} else 
	if ($mnc == 91) {$mncname = 'Vodafone Uk Ltd';} else 
	if ($mnc == 92) {$mncname = 'Vodafone Ltd (C&W)';} else 
	if ($mnc == 94) {$mncname = 'Hutchison 3G UK Ltd';} else 
	if ($mnc == 95) {$mncname = 'Network Rail Infrastructure Limited';} else 
									{$mncname = "Unknown Network ($mnc)";}

	return($mncname);
}
?>
