from pyspark.sql import SparkSession
from pyspark.sql.functions import col, split, regexp_replace, trim

# Create SparkSession
spark = SparkSession.builder \
    .appName("ParsePipeDelimitedData") \
    .getOrCreate()

# Read the data file
df = spark.read.text("data.txt")

# Filter out empty lines
df = df.filter(trim(col("value")) != "")

# Split by pipe delimiter
df_split = df.withColumn("fields", split(col("value"), "\\|"))

# Remove quotes from each field and trim whitespace
# Create columns for each of the 6 fields
df_cleaned = df_split.select(
    trim(regexp_replace(col("fields")[0], '^"|"$', '')).alias("field0"),
    trim(regexp_replace(col("fields")[1], '^"|"$', '')).alias("field1"),
    trim(regexp_replace(col("fields")[2], '^"|"$', '')).alias("field2"),
    trim(regexp_replace(col("fields")[3], '^"|"$', '')).alias("field3"),
    trim(regexp_replace(col("fields")[4], '^"|"$', '')).alias("field4"),
    trim(regexp_replace(col("fields")[5], '^"|"$', '')).alias("field5")
)

# Show the results
print("Parsed and cleaned data (quotes removed):")
df_cleaned.show(truncate=False)

# Print schema
print("\nSchema:")
df_cleaned.printSchema()

# Stop SparkSession
spark.stop()

