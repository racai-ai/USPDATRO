import torch
import evaluate
import os
import csv
import unicodedata
import re

ref="/data/CORPORA/USPDATRO/CORPUS/processed/text"
meta="/data/CORPORA/USPDATRO/CORPUS/processed/metadata.csv"
pred="./ds2"

metrics_wer=evaluate.load("wer")
metrics_cer=evaluate.load("cer")

r_list={"all":{"all":[]}, "type":{}, "age":{}, "sex":{}, "type_sex":{}, "type_sex_age":{}}
p_list={"all":{"all":[]}, "type":{}, "age":{}, "sex":{}, "type_sex":{}, "type_sex_age":{}}

print("Reference=",ref)
print("Predictions=",pred)
print("Meta=",meta)

metadata={}

with open(meta,"r") as csvFile:
    csvReader=csv.reader(csvFile,delimiter=';',quotechar='"')
    rowNum=0
    for row in csvReader:
        rowNum+=1
        if rowNum==1: continue
        metadata[row[0]+".txt"]={"age":row[18],"sex":row[17],"type":row[4],"type_sex":row[4]+"_"+row[17],"type_sex_age":row[4]+"_"+row[17]+"_"+row[18]}

#print(metadata)

def remove_symbols(s: str):
        """
        Replace any other markers, symbols, punctuations with a space, keeping diacritics
        """
        return "".join(
            " " if unicodedata.category(c)[0] in "MSP" else c
            for c in unicodedata.normalize("NFKC", s)
        )

def clean_text(s: str):
        text=s.lower()
        text=remove_symbols(text)
        text = re.sub(
            r"\s+", " ", text
        )  # replace any successive whitespace characters with a space
        text=text.strip()
        return text

for f in os.listdir(ref):
    fref=os.path.join(ref,f)
    fpred=os.path.join(pred,f)
    if os.path.isfile(fref) and os.path.isfile(fpred):
        with open(fref,"r") as fin:
            text=fin.read().strip().lower()
            text=clean_text(text)

            r_list["all"]["all"].append(text)

            if metadata[f]["type"] not in r_list["type"]: r_list["type"][metadata[f]["type"]]=[]
            r_list["type"][metadata[f]["type"]].append(text)

            if metadata[f]["age"] not in r_list["age"]: r_list["age"][metadata[f]["age"]]=[]
            r_list["age"][metadata[f]["age"]].append(text)

            if metadata[f]["sex"] not in r_list["sex"]: r_list["sex"][metadata[f]["sex"]]=[]
            r_list["sex"][metadata[f]["sex"]].append(text)

            if metadata[f]["type_sex"] not in r_list["type_sex"]: r_list["type_sex"][metadata[f]["type_sex"]]=[]
            r_list["type_sex"][metadata[f]["type_sex"]].append(text)

            if metadata[f]["type_sex_age"] not in r_list["type_sex_age"]: r_list["type_sex_age"][metadata[f]["type_sex_age"]]=[]
            r_list["type_sex_age"][metadata[f]["type_sex_age"]].append(text)

        with open(fpred,"r") as fin:
            text=fin.read().strip().lower()
            text=clean_text(text)
            #print(text)

            p_list["all"]["all"].append(text)

            if metadata[f]["type"] not in p_list["type"]: p_list["type"][metadata[f]["type"]]=[]
            p_list["type"][metadata[f]["type"]].append(text)

            if metadata[f]["age"] not in p_list["age"]: p_list["age"][metadata[f]["age"]]=[]
            p_list["age"][metadata[f]["age"]].append(text)

            if metadata[f]["sex"] not in p_list["sex"]: p_list["sex"][metadata[f]["sex"]]=[]
            p_list["sex"][metadata[f]["sex"]].append(text)

            if metadata[f]["type_sex"] not in p_list["type_sex"]: p_list["type_sex"][metadata[f]["type_sex"]]=[]
            p_list["type_sex"][metadata[f]["type_sex"]].append(text)

            if metadata[f]["type_sex_age"] not in p_list["type_sex_age"]: p_list["type_sex_age"][metadata[f]["type_sex_age"]]=[]
            p_list["type_sex_age"][metadata[f]["type_sex_age"]].append(text)



print("Total files=",len(r_list["all"]))

for key1 in r_list:
    print("Statistics by ",key1)

    for key2 in r_list[key1]:
        wer=metrics_wer.compute(references=r_list[key1][key2], predictions=p_list[key1][key2])
        cer=metrics_cer.compute(references=r_list[key1][key2], predictions=p_list[key1][key2])

        print("   ",key2," WER=",round(wer,4),"   (",len(r_list[key1][key2])," samples)")
        print("   ",key2," CER=",round(cer,4))
